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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table,
        th,
        td {
            border: 1px solid #ddd;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f4f4f4;
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

        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #110505;
        }

        .textContent {
            color: #110505;
            text-align: left; /* Align text to the left */
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="email-container">
       
            <img src="{{ $propertyImageUrl }}" alt="Property Image" class="logo">
            <h3>Leads Import Report: Skipped or Failed Leads Detected for {{ ucfirst($property->name) }} Property</h3>

            <div class="textContent">
                <p>Hello {{ ucfirst($property->user->name) }},</p>
                <p>Attached is the CSV file containing the details of skipped or failed leads. Please review the data to understand the reasons for the skipped or failed entries.</p>
                <p>If you have any questions, feel free to reach out.</p>
            </div>
      
        <div class="footer">
            © {{ date('Y') }} All rights reserved.
        </div>
    </div>
</body>

</html>
