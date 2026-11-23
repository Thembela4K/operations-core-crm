<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Leave Application &mdash; {{ $leave->user->name }}</title>
</head>
<body>
    @include('leave.partials.datf025', ['leave' => $leave])
</body>
</html>
