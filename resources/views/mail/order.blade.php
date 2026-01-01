<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $email_title }}</title>
</head>
<body>
    <p>Hallo,</p>

    <p>hier die Bestellung vom Lagersystem aus der Wache Coswig.</p>

    <p>
        Vom: <b>{{ $fromDate }}</b><br>
        Gültig bis: <b>{{ $duetoDate }}</b>
    </p>

    <p>
      Im Anhang befinden sich die Bestellformulare.
    </p>

    <p>
        MfG,<br>
        Lagersystem Coswig
    </p>
</body>
</html>
