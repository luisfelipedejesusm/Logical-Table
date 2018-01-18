# Logical-Table

This is a simple class to generate Logical Tables given a Logical Expression.

Logical Operators Alowed are:


  
  > AND ( & )
  
  > OR ( | )
  
  > IF THEN ( > )
  
  > ONLI IF ( = )
  
  > NOT ( ~ )
  
  
  
After Instancing the class you can get the generated html object by callind function with() where you will insert your expression and then get()



```php
$LTable = new LTable;

$expression = "~( A | B ) > C";

$table->with($expression)->get(); // this will generate a HTML <table> in String format
```
