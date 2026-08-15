<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->title }}</title>
    <style>
        body {
            font-family: sans-serif;
            text-align: center;
            padding: 2rem;
        }
        .title {
            font-size: 28px;
            font-weight: 700;
            color: #111;
        }
        .date {
            font-size: 16px;
            color: #444;
            margin-top: 8px;
        }
        .type {
            font-size: 14px;
            font-weight: 700;
            color: #20673A;
            margin-top: 10px;
        }
        .range {
            font-size: 14px;
            color: #555;
            margin-top: 4px;
        }
        .qr {
            margin-top: 28px;
            text-align: center;
        }
        .qr img {
            width: 90%;
        }
    </style>
</head>
<body>
    <div class="title">{{ $event->title }}</div>
    <div class="date">{{ $date }}</div>
    <div class="date">{{ $timeRange }}</div>
    <div class="type">{{ $type }}</div>
    <div class="range">Valid: {{ $validRange }}</div>
    <div class="qr">
        <img src="{{ $qr }}" alt="QR Code" />
    </div>
</body>
</html>
