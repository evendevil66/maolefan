## 关于项目

猫乐饭是一款后端基于PHP的返利APP。

本项目使用 [Laravel](https://laravel.com/) 作为主架构进行开发，管理后台基于 [X-admin](http://x.xuebingsi.com)二次开发。
本项目使用GPLv3协议，允许复制、传播、修改及商业使用，禁止将修改后和衍生的代码做为闭源的商业软件发布和销售。
目前仅对后端进行开源学习，前端代码暂不开源。

仅用于个人学习使用或小范围使用（不超过100人且不上架、不进行任何推广），如需用于商业用途，请联系我司授权，联系邮箱【zhangjiaqi@maomengte.com】，如您对前端代码感兴趣也可以通过邮件进行沟通。  
《猫乐饭》拥有软件著作权证书，未经我司允许擅自用于商业用途及上架，我司将保留追究法律责任的权利。  
请勿针对本项目源码进行分析、破解、攻击等行为，如有发现，我们将保留追究法律责任的权利。  
我们非常欢迎您共同维护本项目，对于帮助我们有效改进代码、修复bug的开发者，我们将免费授权给您《猫乐饭》的商业使用权。

注意：本项目仅为后端代码，无法独立运行，需要有一定的PHP开发基础，且需要配合前端代码使用。

## 对接API

本项目主要使用 [淘宝联盟](https://pub.alimama.com/) 、 [大淘客](https://www.dataoke.com) 等平台接口进行开发

## 项目体验

<h4>项目目前已上架各大安卓应用市场，欢迎体验</h4>

下载体验：[官方站](https://fanli.maomengte.com/download)、[应用宝](https://sj.qq.com/appdetail/com.maomengte.mlf)

## 主要配置文件

1、/config/config.php &nbsp;&nbsp;&nbsp;&nbsp; #本配置文件保存站点/平台基本信息、淘宝联盟和大淘客APPKEY等信息  
2、.env &nbsp;&nbsp;&nbsp;&nbsp; #本配置文件保存数据库相关信息

## 已实现功能

1、复制转链，parseApi接口传入用户id、待转链链接或口令，返回转链后的链接  
2、淘礼金直返，createTlj接口传入用户id、token校验、商品信息等，创建并返回淘礼金信息  
3、渠道返利，支持绑定淘宝账号获取渠道id，便于自动跟单

更多功能请前往APP体验

## TODO

功能仍在逐渐开发中，也可以自行去开发相关功能，大家的Star是我持续开发的动力

## Update

2022.11.7
首次开源后端代码

每次更新后请在网页根目录执行以下命令清空缓存，以免因缓存导致部分业务无法访问

````shell script
php artisan cache:clear
php artisan route:cache
````

## 部署方法

环境要求：PHP >= 8（支持PHP8） ｜ MySQL/MariaDB ｜ Redis

注意：项目中使用的工具类SendSms.php使用的为华为云短信服务，如需使用短信功能，请自行修改SendSms.php中的相关配置。如不使用短信验证功能，请修改注册、找回密码等相关接口  
项目并非部署后直接可用，需要自行开发前端代码，API接口文档请参考：[API文档](https://console-docs.apipost.cn/preview/2f87880934dd513c/1fd38acf3b8ff15d)
，API文档暂不完整，正在持续完善中

下载或clone项目代码到所需环境

````PHP
git clone -b master https://github.com/evendevil66/maolefan.git
````

在项目目录下执行Composer命令安装依赖包及自动加载

````shell script
composer install
composer dump-auto
````

复制.env.example文件为.env

````shell script
cp .env.example .env
````

修改.env中的数据库配置及Redis配置并导入项目根目录下的 maolefan.sql 到数据库

````text
DB_CONNECTION=mysql  #默认使用mysql请勿修改 可支持MariaDB
DB_HOST=127.0.0.1  #数据库连接地址
DB_PORT=3306  #数据库连接端口
DB_DATABASE=taolefan #数据库名
DB_USERNAME=root  #数据库用户名
DB_PASSWORD=  #数据库密码

REDIS_HOST=127.0.0.1  #Redis连接地址
REDIS_PASSWORD=null #Redis密码 未设置默认为null
REDIS_PORT=6379 #Redis端口
````

接下来请先完成以下步骤：  
1、淘宝联盟开放平台 创建应用（应用类型可以选择网站） 获取AppKey [官网](https://aff-open.taobao.com)  
2、注册大淘客开放平台并授权淘宝联盟 获取Appkey  [官网](https://www.dataoke.com/kfpt/openapi.html)
3、如需淘宝私域管理功能（自动跟单），请在淘宝联盟申请好私域权限，申请邀请码。邀请码可通过调试 [官方接口](https://open.taobao.com/doc.htm?spm=a219a.15212433.0.0.4398669aXaoE2Y&docId=1&docType=15&apiName=taobao.tbk.sc.invitecode.get)
进行快速申请。
4、注册京东联盟并申请APIKey，授权绑定到大淘客

修改/config/config.php配置

````php
    'name' => "猫乐饭", //产品名称 会反应在用户交互等场景
    'url' => "https://*.*.*", //站点url
    'apiUrl' => "https://*.*.*", //站点url
    'dtkAppKey' => "******", //大淘客appKey 使用大淘客接口快速解析商品信息
    'dtkAppSecret' => "******", //大淘客AppSecret
    'aliAppKey' => "******", //淘宝联盟AppKey
    'aliAppSecret' => "******", //淘宝联盟AppSecret
    'pubpid' => '******', //公用PID
    'specialpid' => ' ******',//渠道PID
    'relationId'=>'******', //渠道ID
    'inviter_code'=>'******', //会员管理邀请码
    'default_rebate_ratio' => 70, //默认返利比例%,
    'eleme_url' => "******",//饿了么小程序路径
    'unionId' => "******", //京东联盟ID
    'jdApiKey' => "******", //京东联盟APIKey
    'invite'=>1, //是否开启邀请 开启填写1 关闭填写0
    'invite_ratio'=>10, //邀请返利比例%
    'invite_rewards'=>1, //邀请奖励金额
````

````
访问管理员注册页面创建超级管理员
````shell script
http://你的域名/adminReg
#该页面仅能创建一次超级管理员，如果后续忘记超级管理员账号密码
#删除站点目录下/storage/app/admin.lock文件后即可重新创建
````

设置定时器crontab用于查询并存储订单

````shell script
crontab -e
````

````PHP
* * * * * curl 你的域名/getOrderList
#每分钟查询一次订单信息并存入数据库
10 1 1,10,19,28 * * curl 你的域名/updateOrderAll
#每个月1、10、19、28日1点10分执行对上月及上上月订单的信息修改及结算等（仅联盟结算日期为上月的才会被结算）
````

至此，猫乐饭项目已经部署完成，请根据[Api文档](https://console-docs.apipost.cn/preview/2f87880934dd513c/1fd38acf3b8ff15d)进行开发。



