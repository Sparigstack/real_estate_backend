<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #3032601f;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            position: relative;
            text-align: center;
            background-repeat: no-repeat;
            background-size: 200px;
            background-position: center;
            background-opacity: 0.1;
        }
        .logo {
            width: 100px;
            margin-bottom: 20px;
            color: #110505;
        }
        .content {
            z-index: 1;
            position: relative;
        }
        .otp {
            font-size: 24px;
            font-weight: bold;
            color: #110505;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #110505;
        }
        .textContent
        {
            color: #110505;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="content">
            <img src="http://superbuildup-9.ap-south-1.elasticbeanstalk.com/real-estate-logo.svg" alt="Site Logo" class="logo">
            <h1 class="textContent">Your OTP Code</h1>
            <p class="textContent">Use the following OTP to complete your authentication:</p>
            <p class="otp">{{ $otp }}</p>
            <p class="textContent">This OTP is valid for 3 minutes. Please do not share it with anyone.</p>
        </div>
        <div class="footer">
            © {{ date('Y') }} All rights reserved.
        </div>
    </div>
</body>
</html>
