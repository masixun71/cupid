<?php
declare(strict_types=1);

namespace Jue\Cupid\ProcessPool;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Jue\Cupid\Loggers\LoggerManager;
use Jue\Cupid\Managers\IdManager;
use Jue\Cupid\Managers\PdoManager;
use Jue\Cupid\Notification\PushBear;
use Jue\Cupid\SqlEvent;
use Noodlehaus\Config;
use Swoole\Channel;
use Swoole\Process;
use Swoole\Process\Pool;
use Swoole\Runtime;
use Swoole\Table;

class ProcessPool
{
    /**
     * @var Table
     */
    private $table;

    /**
     * @var Table
     */
    private $collectTable;

    /**
     * @var Channel
     */
    private $chan;


    private $workNumber;

    /** @var Config */
    private $conf;

    public function __construct($conf)
    {
        $this->conf = $conf;
    }


    public function start()
    {
        $workerNum = $this->conf->get('workerNumber', 3);
        $this->workNumber = $workerNum;
        $pool = new Pool($workerNum);

        $table = new Table(1024);
        $table->column('currentId', Table::TYPE_INT, 8);
        $table->column('nextId', Table::TYPE_INT, 8);
        $table->create();
        $this->table = $table;
        $collectTable = new Table(1024);
        $collectTable->column('memory', Table::TYPE_INT, 8);
        $collectTable->create();
        $this->collectTable = $collectTable;

        $chan = new Channel(1024);
        $this->chan = $chan;

        $pool->on("WorkerStart", function ($pool, $workerId) {
            LoggerManager::newLogger($this->conf->get('logDir'), $workerId);
            if ($workerId == 0) {
                $this->initManagerWorker();
            } elseif ($workerId == 1) {
                $this->initCallbackWorker();
            } else {
                $this->initOtherWorker($workerId);
            }
        });

        $pool->on("WorkerStop", function ($pool, $workerId) {
            echo "Worker#{$workerId} is stopped\n";
        });

        $pool->start();
    }

    private function initManagerWorker() {

        @swoole_set_process_name("cupid manager-worker");

        $dsn = $this->conf->get('src.dsn');
        $user = $this->conf->get('src.user');
        $password = $this->conf->get('src.password');

        logger()->info('本次数据同步补偿关心', [
            'table' => $this->conf->get('src.table')
        ]);


        $pdoManager = new PdoManager($dsn, $user, $password);
        $idManager = new IdManager();
        $idManager->setCurrentId($this->conf->get('src.insertStartId', 0));
        $cacheFilePath = $this->conf->get('src.cacheFilePath') . '/cupid.txt';

        Process::signal(SIGTERM, function () use ($idManager, $cacheFilePath){
            $cacheFile = fopen($cacheFilePath, "w");
            fwrite($cacheFile, (string)$idManager->getCurrentId());
            fclose($cacheFile);
            logger()->info('写入cacheFile最后id值成功');
        });
        Process::signal(SIGCHLD, function () use ($idManager, $cacheFilePath){
            $cacheFile = fopen($cacheFilePath, "w");
            fwrite($cacheFile, (string)$idManager->getCurrentId());
            fclose($cacheFile);
            logger()->info('写入cacheFile最后id值成功');
        });
        if (file_exists($cacheFilePath)) {
            $cacheFile = fopen($cacheFilePath, "r");
            $insertStartId = fread($cacheFile,filesize($cacheFilePath));
            fclose($cacheFile);
            if (!empty($insertStartId)) {
                logger()->info('从cacheFile获取到缓存id值成功');
                $idManager->setCurrentId((int)$insertStartId);
            }
        }

        swoole_timer_tick(100, function ($timeId, $idManager) {
            /** @var IdManager $idManager */
            for ($i = 2; $i < $this->workNumber; $i++) {
                $key = (string)$i;
                $tableI = $this->table->get($key);
                if ($tableI['currentId'] == $tableI['nextId']) {
                    if ($idManager->hasNewId()) {
                        logger()->info('table中有新id', [
                            'newId' => $idManager->getCurrentId()
                        ]);
                        $this->table->set($key, ['nextId' => $idManager->getCurrentId()]);
                        $idManager->incrCurrentId();
                    }
                }
            }

            $memory = (int)(memory_get_usage(true) / (1024 * 1024));
            $this->collectTable->set('0', ['memory' => (string)$memory]);

        }, $idManager);


        if ($this->conf->get('src.insert', false)) {
            swoole_timer_tick($this->conf->get('src.insertIntervalMillisecond'), function ($timeId, $p) {
                /** @var PdoManager $pdoManager */
                /** @var IdManager $idManager */
                list($pdoManager, $idManager) = $p;
                try {
                    $stmt = $pdoManager->getPdo()->query('SELECT max(id) as maxId from ' . $this->conf->get('src.table'), \PDO::FETCH_ASSOC);
                    $res = $stmt->fetch(\PDO::FETCH_ASSOC);
                } catch (\PDOException $e) {
                    if ($e->getCode() == "HY000") {
                        $pdoManager->connect();
                        $stmt = $pdoManager->getPdo()->query('SELECT max(id) as maxId from ' . $this->conf->get('src.table'), \PDO::FETCH_ASSOC);
                        $res = $stmt->fetch(\PDO::FETCH_ASSOC);
                    } else {
                        throw  $e;
                    }
                }
                if ($idManager->getMaxId() < (int)$res['maxId']) {
                    logger()->info('insert事件中监听到新数据插入', [
                        'newId' => $res['maxId']
                    ]);
                    $idManager->setMaxId((int)$res['maxId']);
                }
            }, [$pdoManager, $idManager]);
        }
        if ($this->conf->get('src.update', false)) {
            if (is_null($this->conf->get('src.updateScanSecond'))) {
                throw new \Exception("you need set src.updateScanSecond");
            }
            swoole_timer_tick($this->conf->get('src.updateIntervalMillisecond'), function ($timeId, $p) {
                /** @var PdoManager $pdoManager */
                /** @var IdManager $idManager */
                list($pdoManager, $idManager) = $p;
                $end = time();
                $updateScanSecond = $this->conf->get('src.updateScanSecond');
                $begin = $end - $updateScanSecond;
                $updateSql = 'SELECT id from ' . $this->conf->get('src.table') . ' where ' . $this->conf->get('src.updateColumn') .' between \'' . date($this->conf->get('src.updateTimeFormate'), $begin) . '\' and \'' . date($this->conf->get('src.updateTimeFormate') . '\'', $end);
                try {
                    $stmt = $pdoManager->getPdo()->query($updateSql, \PDO::FETCH_ASSOC);
                    $res = $stmt->fetchAll();
                } catch (\PDOException $e) {
                    if ($e->getCode() == "HY000") {
                        $pdoManager->connect();
                        $stmt = $pdoManager->getPdo()->query($updateSql, \PDO::FETCH_ASSOC);
                        $res = $stmt->fetchAll();
                    } else {
                        throw  $e;
                    }
                }
                if (!empty($res)) {
                    foreach ($res as $id) {
                        $loop = true;
                        while ($loop) {
                            /** @var IdManager $idManager */
                            for ($i = 2; $i < $this->workNumber; $i++) {
                                $key = (string)$i;
                                $tableI = $this->table->get($key);
                                if ($tableI['currentId'] == $tableI['nextId']) {
                                    logger()->info('update事件中监听到有数据更新', [
                                        'updateId' => $id['id']
                                    ]);
                                    $this->table->set($key, ['nextId' => (int)$id['id']]);
                                    $loop = false;
                                    break;
                                }
                            }
                        }
                    }
                }

            }, [$pdoManager, $idManager]);
        }

        swoole_timer_tick(10000, function ($timeId){
            /** @var IdManager $idManager */
            for ($i = 0; $i < $this->workNumber; $i++) {
                $key = (string)$i;
                $tableI = $this->collectTable->get($key);
                if ($tableI) {
                    $memory = $tableI['memory'];

                    logger()->info('进程当前内存值', [
                        'workerId' => $i,
                        'memory(MB)' => $memory
                    ]);
                }
            }

        });


    }

    private function initCallbackWorker() {
        @swoole_set_process_name("cupid callback-worker");


        $pushbearSendKey = $this->conf->get('src.pushbearSendKey');

        swoole_timer_tick($this->conf->get('callbackWorkerIntervalMillisecond'), function () use ($pushbearSendKey) {
            /** @var SqlEvent $sqlEvent */
            $sqlEvent = $this->chan->pop();
            if (!empty($sqlEvent)) {
                logger()->info('从回调队列取出回调数据', [
                    'sqlEvent' => $sqlEvent->toArray()
                ]);
                $client = new Client();
                try {
                    $res = $client->request('POST', $sqlEvent->getCallbackUrl(), [
                        'json' => [
                            'type' => $sqlEvent->getType(),
                            'srcColumn' => $sqlEvent->getSrcColumn()
                        ]
                    ]);
                    if ($res->getStatusCode() != 200) {
                        if ($sqlEvent->getRetriesTime() == 10 && !empty($pushbearSendKey)) {
                            logger()->error('数据同步补偿数据回调地址已超过'.$sqlEvent->getRetriesTime().'次调用不成功,重新push到回调队列', [
                                'sqlEvent' => $sqlEvent->toArray(),
                                'resStatusCode' => $res->getStatusCode()
                            ]);
                            PushBear::push($pushbearSendKey, '数据同步补偿数据回调地址已超过'.$sqlEvent->getRetriesTime().'次调用不成功:请及时检查回调地址', "## sqlEvent\n\n`" . $sqlEvent->toJson() . "`\n\n## responseStatus\n\n" . $res->getStatusCode());
                        }
                        $sqlEvent->incrRetriesTime();
                        $this->chan->push($sqlEvent);
                    } else {
                        logger()->info('数据同步补偿数据回调成功', [
                            'sqlEvent' => $sqlEvent->toArray(),
                            'resStatusCode' => $res->getStatusCode()
                        ]);
                        PushBear::push($pushbearSendKey, '数据同步补偿数据回调地址成功', "## sqlEvent\n\n`" . $sqlEvent->toJson() . "`");
                    }
                } catch (GuzzleException $exception) {
                    logger()->error('数据同步补偿数据回调地址无法请求,重新push到回调队列', [
                        'sqlEvent' => $sqlEvent->toArray(),
                        'exception' => $exception->getMessage()
                    ]);
                    PushBear::push($pushbearSendKey, '数据同步补偿数据回调地址无法请求', "## sqlEvent\n\n`" . $sqlEvent->toJson() . "`\n\n## errorMessage\n\n" . $exception->getMessage());
                    $sqlEvent->incrRetriesTime();
                    $this->chan->push($sqlEvent);
                }
            }
            $memory = (int)(memory_get_usage(true) / (1024 * 1024));
            $this->collectTable->set('1', ['memory' => (string)$memory]);
        });

    }



    private function initOtherWorker($workerId) {

        @swoole_set_process_name("cupid tasker-worker-" . $workerId);

        if (version_compare(SWOOLE_VERSION, '4.0.4', '1')) {
            Runtime::enableCoroutine();
        }

        $key = (string)$workerId;

        go(function () use ($key) {
            $dsn = $this->conf->get('src.dsn');
            $user = $this->conf->get('src.user');
            $password = $this->conf->get('src.password');
            $pdoSrc = new PdoManager($dsn, $user, $password);

            $pdoDess = [];
            foreach ($this->conf->get('des') as $des) {
                $dsn = $des['dsn'];
                $user = $des['user'];
                $password = $des['password'];
                $pdoDess[] = new PdoManager($dsn, $user, $password);
            }

            swoole_timer_tick($this->conf->get('taskWorkerIntervalMillisecond'), function () use ($key, $pdoSrc, $pdoDess) {
                $tableI = $this->table->get($key);
                if ($tableI) {
                    if ($tableI['currentId'] != $tableI['nextId']) {
                        $srcSql = 'SELECT * FROM ' . $this->conf->get('src.table') . ' where id= ?';
                        try {
                            $sth = $pdoSrc->getPdo()->prepare($srcSql);
                            $sth->execute([$tableI['nextId']]);
                            $srcArray = $sth->fetch(\PDO::FETCH_ASSOC);
                        } catch (\PDOException $e) {
                            if ($e->getCode() == "HY000") {
                                $pdoSrc->connect();
                                $sth = $pdoSrc->getPdo()->prepare($srcSql);
                                $sth->execute([$tableI['nextId']]);
                                $srcArray = $sth->fetch(\PDO::FETCH_ASSOC);
                            } else {
                                throw  $e;
                            }
                        }
                        if (!empty($srcArray)) {
                            foreach ($pdoDess as $keyPdo => $pdoDes) {
                                $desSql = 'SELECT * FROM ' . $this->conf['des'][$keyPdo]['table'] . ' where ' . $this->conf['des'][$keyPdo]['byColumn'] . '= ?';
                                $byColumn = $srcArray[$this->conf->get('src.byColumn')];
                                try {
                                    /** @var PdoManager $pdoDes */
                                    $sth = $pdoDes->getPdo()->prepare($desSql);
                                    $sth->execute([$byColumn]);
                                    $desArray = $sth->fetch(\PDO::FETCH_ASSOC);
                                } catch (\PDOException $e) {
                                    if ($e->getCode() == "HY000") {
                                        $pdoDes->connect();
                                        $sth = $pdoDes->getPdo()->prepare($desSql);
                                        $sth->execute([$byColumn]);
                                        $desArray = $sth->fetch(\PDO::FETCH_ASSOC);
                                    } else {
                                        throw  $e;
                                    }
                                }

                                if (empty($desArray)) {
                                    $this->chan->push(new SqlEvent(SqlEvent::INSERT, $srcArray, $this->conf['des'][$keyPdo]['callbackNotification']['url']));
                                    logger()->error('数据同步检查发现缺少数据,push到回调队列', [
                                        'srcTable' => $this->conf->get('src.table'),
                                        'srcByColumn' => $this->conf->get('src.byColumn'),
                                        'desTable' =>$this->conf['des'][$keyPdo]['table'],
                                        'desByColumn' => $this->conf['des'][$keyPdo]['byColumn'],
                                        'srcArray' => $srcArray,
                                    ]);
//                                    var_dump('缺少数据' . $this->conf['des'][$keyPdo]['table'] . ':' . $this->conf['des'][$keyPdo]['byColumn'] . $byColumn);
                                } else {
                                    foreach ($this->conf['des'][$keyPdo]['columns'] as $keyColumn => $column) {

                                        if ($srcArray[$keyColumn] != $desArray[$column]) {
                                            $this->chan->push(new SqlEvent(SqlEvent::UPDATE, $srcArray, $this->conf['des'][$keyPdo]['callbackNotification']['url']));
                                            logger()->error('数据同步检查发现数据对不上,push到回调队列', [
                                                'srcTable' => $this->conf->get('src.table'),
                                                'srcByColumn' => $this->conf->get('src.byColumn'),
                                                'desTable' =>$this->conf['des'][$keyPdo]['table'],
                                                'desByColumn' => $this->conf['des'][$keyPdo]['byColumn'],
                                                'srcArray' => $srcArray,
                                                'desArray' => $desArray
                                            ]);
//                                            var_dump('数据不准确' . $this->conf['des'][$keyPdo]['table'] . ':' . $this->conf['des'][$keyPdo]['byColumn'] . ':' . $byColumn .':'. $srcArray[$keyColumn] . ':' . $desArray[$column]);
                                        }

                                    }
                                }
                            }
                        }

                        $this->table->set($key, ['currentId' => $tableI['nextId']]);
                    }

                }
                $memory = (int)(memory_get_usage(true) / (1024 * 1024));
                $this->collectTable->set($key, ['memory' => (string)$memory]);
            });

        });
    }



}