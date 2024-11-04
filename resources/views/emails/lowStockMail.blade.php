<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Low-Quantity Alert</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: black !important;
            margin: 0;
            padding: 20px;
            background-color: #ffffff;
        }
        h2 {
            margin: 0;
            font-size: 24px;
            color: black !important;
        }
        p {
            line-height: 1.5;
            margin: 0.5em 0;
            font-weight: 500;
            color: black !important;
        }
        .footer {
            text-align:start;
            padding: 10px 0;
            color: black !important;
        }
    </style>
</head>
<body>
   <h2>Low-Stock Alert</h2> 
   <p>Dear {{ $data['userName']?? '-' }},</p>
    <p>We wanted to inform you that the stock level for <strong>{{ $data['inventoryName'] ?? '-' }}</strong> 
        has fallen below the minimum threshold you set.</p> 
        <p><strong>Item Name:</strong> {{ $data['inventoryName']?? '-' }}</p> 
        <p><strong>Current Quantity Level:</strong> {{ $data['currentStock'] ?? '-' }}</p> 
        <p><strong>Minimum Required Quantity:</strong> {{ $data['reminderStock'] ?? '-' }}</p> 
        <p>Please take the necessary steps to replenish this item to avoid any disruptions.</p> <div class="footer">
        <p>Thank you for your attention!</p> </div>
    </div>
</body>
</html>
