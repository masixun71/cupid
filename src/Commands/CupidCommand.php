<?php

namespace Jue\Cupid\Commands;


use Jue\Cupid\ProcessPool\ProcessPool;
use Noodlehaus\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CupidCommand extends Command
{
    /** @var InputInterface $input */
    private $input;
    /** @var OutputInterface $output */
    private $output;


    protected function configure()
    {
        $this->setName('cupid')
            ->setDescription('mysql-table-compare-and-fix')
            ->setHelp('This command allows you to create cupid');

        $this->addArgument('do', InputArgument::REQUIRED)
            ->addArgument('config_path', InputArgument::OPTIONAL)
            ->addArgument('start_id', InputArgument::OPTIONAL);

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $do = $this->input->getArgument('do');

        switch ($do) {
            case 'start':
                $configPath = $this->input->getArgument('config_path');
                $startId = $this->input->getArgument('start_id');
                $conf = Config::load($configPath);
                if (!empty($startId)) {
                    $conf->set('src.insertStartId', $startId);
                }
                $this->start($conf);
                break;
            case 'help':
            default:
                $this->showHelp();
                break;
        }
    }


    private function start($conf)
    {
        $pool = new ProcessPool($conf);

        $pool->start();
    }

    private function showHelp()
    {
        $io = new SymfonyStyle($this->input, $this->output);

        $io->title("<info>cupid基本使用帮助[cupid help]</info>");
        $io->section("推荐配合supervisord 使用");
        $io->section("目前提供2个命令使用,start, help");
        $io->section('cupid 是什么？');
        $io->writeln("cupid 是基于swoole4.0 processPool 开发的消息同步补偿工具，适用于在<comment>canal</comment>这类的实时同步数据中间件之外的同步补偿工具，也适用于实时缓存这类的及时更新工具，对数据同步进行双保险，一般canal的延迟在毫秒左右，cupid建议设置在秒左右，做补偿专用，canal的消费端嵌入业务代码可以更方便开发和消费，cupid作为更通用的补偿方案建议不要嵌入业务代码，补偿机制采用http回调来保证，失败会重试直到成功，通知采用<comment>pushbear</comment>微信即时通知");
        $io->section('cupid 要求');
        $io->listing(array(
            '<info>php >= 7.2</info>',
            '<info>swoole >= 4.2.12</info>',
        ));
        $io->section('help');
        $io->listing(array(
            '查看基本使用帮助<info>[basic help]</info>',
        ));
        $io->section('start');
        $io->writeln("start 命令后面需要2个参数<info>[The start command requires two arguments]</info>: start <comment>config_path</comment> <comment>start_id</comment>");
        $io->listing(array(
            '<comment>config_path</comment>: config配置的路径，需要绝对路径 ，对config配置有问题可以看文档或者testConfig.json',
            '<comment>start_id</comment>: 起始的数据库表id, 优先级：上次的缓存文件存的id值> shell命令传入的start_id > 默认值0',
        ));
        $io->success('🙏感谢收看[thank you]');
    }

}