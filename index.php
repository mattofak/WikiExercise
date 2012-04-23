<html><head><title>Test Script</title></head><body>

<?php

include 'CurrencyConverter.php';

$cc = new CurrencyConverter();

print $cc->convert_currency('JPY 5000');
print "<br>";
var_dump($cc->convert_currency(array('JPY 5000', 'CZK 62.5')));

?>

</body></html>
