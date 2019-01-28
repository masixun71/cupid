![](https://img.shields.io/badge/version-v0.0.0.1-red.svg)
![](https://img.shields.io/badge/php-%3E=7.2-orange.svg)
![](https://img.shields.io/badge/swoole-%3E=4.0.4-blue.svg)
# cupid
Data synchronization compensation tool


# 简介
cupid 是基于`swoole4.0 processPool` 开发的消息同步补偿工具，适用于在`canal`这类的实时同步数据中间件之外的同步补偿工具，也适用于实时缓存这类的及时更新工具，对数据同步进行双保险，一般`canal`的延迟在毫秒左右，`cupid`建议设置在秒左右，做补偿专用，`canal`的消费端嵌入业务代码可以更方便开发和消费，`cupid`作为更通用的补偿方案,所以建议不要嵌入业务代码，补偿机制采用http回调来保证，失败会重试直到成功，通知目前采用`pushbear`微信即时通知,简单即时。


# 环境要求


- php 版本最好在7.2以上，目前只测试了7.2的版本，但是理论上7.0以上都可以
- swoole版本在4.0.4 以上，推荐最新版本，更稳定



# 安装步骤

## 1.clone本项目
```php
git clone git@github.com:masixun71/cupid.git
```


## 2.composer 安装

#### 进入到cupid 的项目目录
```php
composer install
```



## 3.填写一个我们的配置文件（任意目录，要求json）
#### 配置详情 (可参考testConfig.json)
```php
    {
    "workerNumber": 3, //最低值3，前2个进程是manager和callback进程，之后的才是处理进程
	"logDir": "/tmp", //日志目录，进程日志会打印到该目录下
	"callbackWorkerIntervalMillisecond": 1000, //回调进程间隔多长时间处理一次
    "taskWorkerIntervalMillisecond": 1000, //task进程间隔多长时间处理一次
    "src": {
        "dsn": "mysql:dbname=my;host=127.0.0.1;port=3306",//数据库dsn配置
        "user": "test",//数据库用户名
        "password": "test",//数据库密码
        "table": "user",//我们关注的数据库表
        "byColumn": "number",//通过该字段对应des的byColumn，进行比对
        "insert": true, //是否关注insert的数据
        "insertIntervalMillisecond": 2000, //检查insert更新的间隔时间，单位毫秒
        "update": true, //是否关注update数据，若为false，则下面update开头的字段可以不用
        "updateColumn": "update_time", //update为true时必填，更新的字段名，需要添加索引，不然会扫描全表
        "updateIntervalMillisecond": 2000, //update为true时必填,检查update更新的间隔时间，单位毫秒
        "updateScanSecond": 5, //update为true时必填,获取数据的时间间隔，当前时间减去updateScanSecond设的时间为开始时间，当前时间为结束时间
        "updateTimeFormate": "Y-m-d H:i:s", //update为true时必填,数据库里数据更新字段的时间格式
        "cacheFilePath": "/tmp", //若进程有异常退出或者重启，会把当前的遍历信息记录到缓存文件中，重启时直接读取缓存文件
        "pushbearSendKey": "9724-73bdacb319007f53f83d0123213b4ec964"//若需要pushbear推送微信消息，在这填写
    },
    "des": [//des是一个数组，意味着我们可同时比对多个数据表
        {
            "dsn": "mysql:dbname=my2;host=127.0.0.1;port=3306",//数据库dsn配置
            "user": "test",//数据库用户名
            "password": "test",//数据库密码
            "table": "user",//我们同步的数据库表
            "columns": { //关注和同步表的字段对应关系
                "number": "number",
                "name": "name",
                "avatar": "avatar"
            },
            "byColumn": "number",//与src的byColumn相呼应，形成关联关系来比对
            "callbackNotification": {
                "url" : "127.0.0.1:20000/test/callback"//当数据不同步时的回调地址
            }

        }
    ]
}
```

# 启动项目
```php
php cupid.php cupid start config_path [start_id]
```

- **config_path**: config配置的路径，需要绝对路径 ，对config配置有问题可以看文档或者testConfig.json
- **start_id**: 起始的数据库表id, 优先级：上次的缓存文件存的id值> shell命令传入的start_id > 默认值0

# 帮助

```php
php cupid.php cupid help
```

# pushbear

pushbear是一个基于微信模板的一对多消息送达服务，使用简单,高效,只需要申请一个key即可。

[pushbear官网](http://pushbear.ftqq.com/admin/#/)



# 回调形式

回调采用的是`POST`形式，Content-Type 为 `application/json`

```php
{
	"type" : 1, 
    "srcColumn" : {
    	"column1": 1,
    	"column2": "2",
    	"column3": 1.2
    }
}
```

- type, 指的是变更类型，insert是1,update是2
- srcColumn, 指的是源数据列，会把整个源数据传给你

### 回调失败后会重新推入队列，继续回调



# supervisord

该项目比较适合搭配supervisord使用,基本上配置文件应该如下

```php
[program:cupid.synchronization-compensation-tool]
directory=/tmp
command=php /tmp/cupid/cupid.php cupid start /tmp/configCupid.json
numprocs=1
autorestart=true
stopsignal=TERM
stopwaitsecs=2
killasgroup=true
user=nobody
stdout_logfile=/tmp/cupid.synchronization-compensation-tool.log
redirect_stderr=true
loglevel=debug

```

如果你不用supervisord, 建议重启进程时使用`kill -s SIGUSR1 $master_pid`



# todo

- 进程异常崩溃后，把队列数据存入文件中，保证下次消费