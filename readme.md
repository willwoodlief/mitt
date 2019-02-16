#Notes:

commands to run:

    sudo apt-get install php7.2-zip
    sudo apt-get install php7.2-mbstring
    sudo apt-get install php7.2-xml
    composer install

After installing with composer, make the following change
see https://github.com/PHPOffice/PhpSpreadsheet/pull/845 to know when to stop doing this

    vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Reader/Csv.php
    line 258
    replace:
    $line = preg_replace('/(' . $enclosure . '.*' . $enclosure . ')/U', '', $line);
    with 
    // Add 's' to the replace rule in order for '.' to also match newline.
    $line = preg_replace('/(' . $enclosure . '.*' . $enclosure . ')/Us', '', $line);

to allow using newlines inside csv fields



spreadsheet docs
https://phpspreadsheet.readthedocs.io/en/develop/#software-requirements
