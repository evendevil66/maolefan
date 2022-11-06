<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0,user-scalable=no">
    <title>猫乐饭</title>


    <style>
        html, body {
            background: #f1d857;
            font-family: 'Ubuntu';
        }

        * {
            box-sizing: border-box;
        }

        .box {
            width: 350px;
            height: 100%;
            max-height: 600px;
            min-height: 450px;
            background: #f7b650;
            border-radius: 20px;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            padding: 30px 50px;
        }

        .box .box__ghost {
            padding: 15px 25px 25px;
            position: absolute;
            left: 50%;
            top: 20%;
            transform: translate(-50%, -30%);
        }

        .box .box__ghost .symbol:nth-child(1) {
            opacity: .2;
            animation: shine 4s ease-in-out 3s infinite;
        }

        .box .box__ghost .symbol:nth-child(1):before, .box .box__ghost .symbol:nth-child(1):after {
            content: '';
            width: 12px;
            height: 4px;
            background: #fff;
            position: absolute;
            border-radius: 5px;
            bottom: 65px;
            left: 0;
        }

        .box .box__ghost .symbol:nth-child(1):before {
            transform: rotate(45deg);
        }

        .box .box__ghost .symbol:nth-child(1):after {
            transform: rotate(-45deg);
        }

        .box .box__ghost .symbol:nth-child(2) {
            position: absolute;
            left: -5px;
            top: 30px;
            height: 18px;
            width: 18px;
            border: 4px solid;
            border-radius: 50%;
            border-color: #fff;
            opacity: .2;
            animation: shine 4s ease-in-out 1.3s infinite;
        }

        .box .box__ghost .symbol:nth-child(3) {
            opacity: .2;
            animation: shine 3s ease-in-out .5s infinite;
        }

        .box .box__ghost .symbol:nth-child(3):before, .box .box__ghost .symbol:nth-child(3):after {
            content: '';
            width: 12px;
            height: 4px;
            background: #fff;
            position: absolute;
            border-radius: 5px;
            top: 5px;
            left: 40px;
        }

        .box .box__ghost .symbol:nth-child(3):before {
            transform: rotate(90deg);
        }

        .box .box__ghost .symbol:nth-child(3):after {
            transform: rotate(180deg);
        }

        .box .box__ghost .symbol:nth-child(4) {
            opacity: .2;
            animation: shine 6s ease-in-out 1.6s infinite;
        }

        .box .box__ghost .symbol:nth-child(4):before, .box .box__ghost .symbol:nth-child(4):after {
            content: '';
            width: 15px;
            height: 4px;
            background: #fff;
            position: absolute;
            border-radius: 5px;
            top: 10px;
            right: 30px;
        }

        .box .box__ghost .symbol:nth-child(4):before {
            transform: rotate(45deg);
        }

        .box .box__ghost .symbol:nth-child(4):after {
            transform: rotate(-45deg);
        }

        .box .box__ghost .symbol:nth-child(5) {
            position: absolute;
            right: 5px;
            top: 40px;
            height: 12px;
            width: 12px;
            border: 3px solid;
            border-radius: 50%;
            border-color: #fff;
            opacity: .2;
            animation: shine 1.7s ease-in-out 7s infinite;
        }

        .box .box__ghost .symbol:nth-child(6) {
            opacity: .2;
            animation: shine 2s ease-in-out 6s infinite;
        }

        .box .box__ghost .symbol:nth-child(6):before, .box .box__ghost .symbol:nth-child(6):after {
            content: '';
            width: 15px;
            height: 4px;
            background: #fff;
            position: absolute;
            border-radius: 5px;
            bottom: 65px;
            right: -5px;
        }

        .box .box__ghost .symbol:nth-child(6):before {
            transform: rotate(90deg);
        }

        .box .box__ghost .symbol:nth-child(6):after {
            transform: rotate(180deg);
        }

        .box .box__ghost .box__ghost-container {
            width: 100%;
            height: 100%;
            position: relative;
            margin: 0 auto;
            animation: upndown 3s ease-in-out infinite;
        }

        .logo {
            width: 100%;
            height: 100%;
            position: relative;
            margin: 0 auto;
            animation: upndown 3s ease-in-out infinite;
        }

        .box .box__ghost .box__ghost-container .box__ghost-eyes {
            position: absolute;
            left: 50%;
            top: 45%;
            height: 12px;
            width: 70px;
        }


        .box .box__description {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
        }

        .box .box__description .box__description-container {
            color: #fff;
            text-align: center;
            width: 200px;
            font-size: 16px;
            margin: 0 auto;
        }

        .box .box__description .box__description-container .box__description-title {
            font-size: 24px;
            letter-spacing: .5px;
        }

        .box .box__description .box__description-container .box__description-text {
            color: #8C8AA7;
            line-height: 20px;
            margin-top: 20px;
        }

        .box .box__description .box__button {
            display: block;
            position: relative;
            background: #FF5E65;
            border: 1px solid transparent;
            border-radius: 50px;
            height: 50px;
            text-align: center;
            text-decoration: none;
            color: #fff;
            line-height: 50px;
            font-size: 18px;
            white-space: nowrap;
            margin-top: 25px;
            transition: background .5s ease;
            overflow: hidden;
        }

        .box__input {
            display: block;
            position: relative;
            border: 1px solid transparent;
            border-radius: 50px;
            height: 50px;
            text-align: center;
            text-decoration: none;
            line-height: 50px;
            padding: 0 70px;
            white-space: nowrap;
            margin-top: 25px;
            transition: background .5s ease;
            overflow: hidden;
        }

        .box .box__description .box__button:before {
            content: '';
            position: absolute;
            width: 20px;
            height: 100px;
            background: #fff;
            bottom: -25px;
            left: 0;
            border: 2px solid #fff;
            transform: translateX(-50px) rotate(45deg);
            transition: transform .5s ease;
        }

        .box .box__description .box__button:hover {
            background: transparent;
            border-color: #fff;
        }

        .box .box__description .box__button:hover:before {
            transform: translateX(250px) rotate(45deg);
        }

        @keyframes upndown {
            0% {
                transform: translateY(5px);
            }
            50% {
                transform: translateY(15px);
            }
            100% {
                transform: translateY(5px);
            }
        }

        @keyframes smallnbig {
            0% {
                width: 90px;
            }
            50% {
                width: 100px;
            }
            100% {
                width: 90px;
            }
        }

        @keyframes shine {
            0% {
                opacity: .2;
            }
            25% {
                opacity: .1;
            }
            50% {
                opacity: .2;
            }
            100% {
                opacity: .2;
            }
        }
    </style>
</head>
<body>
<div class="box">
    <div class="box__ghost">
        <div class="symbol"></div>
        <div class="symbol"></div>
        <div class="symbol"></div>
        <div class="symbol"></div>
        <div class="symbol"></div>
        <div class="symbol"></div>

        <div class="box__ghost-container">
            <img class="logo" src="logo.png"/>
        </div>
    </div>

    <div class="box__description">
        <div class="box__description-container">
            <div class="box__description-title">猫乐饭-饭粒查询</div>
        </div>
        <form action="query" method="get" id="queryForm">
            <input class="box__input" type="value" placeholder="请粘贴要查询的商品信息" name="content"/>
            <a href="#" onclick="document.getElementById('queryForm').submit();" class="box__button">一键查饭粒</a>
        </form>

        @if(isset($data['title']))
            <a href="{{$data['url']}}">
                @endif
                <div style="margin-top: 20px;margin-left: 10px;">
                    @if( !isset($data['title']))
                        <img src="logo.png" width="30%" style="text-align: center;float: left;opacity: 0.8;"/>
                    @else
                        <img src="{{$data['image']}}" width="30%" style="text-align: center;float: left;opacity: 0.8;"/>
                    @endif
                    <div class="box__description-text"
                         style="float: left;padding-top: 10px;color: white;margin-left: 10px;">
                        @if( !isset($data) || $data == null)
                            等待转链<br/>
                            当前QQ：{{$qq}}<br/>
                            <a href="loginout" style="color: white;">点击此处更换QQ</a>
                        @elseif(isset($data['title']) == null)
                            <p>{{$data}}</p>
                        @else
                            查询成功，点此下单<br/>
                            {{$data['couponInfo']}} 预计付{{$data['price']}}<br/>
                            比例{{$data['maxCommissionRate']}}% 预计饭{{$data['estimate']}}
                        @endif
                        <div>

                        </div>
                    </div>

                </div>
                @if(isset($data['title']))
            </a>
        @endif

    </div>


    <script>


        var pageX = $(document).width();
        var pageY = $(document).height();
        var mouseY = 0;
        var mouseX = 0;

        $(document).mousemove(function (event) {
            //verticalAxis
            mouseY = event.pageY;
            yAxis = (pageY / 2 - mouseY) / pageY * 300;
            //horizontalAxis
            mouseX = event.pageX / -pageX;
            xAxis = -mouseX * 100 - 100;

            $('.box__ghost-eyes').css({'transform': 'translate(' + xAxis + '%,-' + yAxis + '%)'});

            //console.log('X: ' + xAxis);

        });</script>

</body>
</html>
