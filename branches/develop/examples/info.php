<?php
ob_start();
phpinfo(INFO_MODULES);
$info = ob_get_clean();

$data = explode(PHP_EOL, $info);

$extension = false;
$hsclient = false;
$version = null;

$section = false;
$blank = 0;

foreach ($data as $key => $val)
{
    if (strcmp($val, 'handlersocket') == 0 || $section)
    {
        $extension = true;
        $section = true;
    }
    else
    {
        continue;
    }

    if (empty($val))
    {
        $blank++;
        if ($blank == 2)
        {
            //$section = false;
            break;
        }
    }

    if (strncmp($val, 'extension Version', 17) == 0)
    {
        $version = str_replace('extension Version => ', '', $val);
    }
    else if (strncmp($val, 'hsclient Library Support', 24) == 0)
    {
        $hsclient = true;
    }
}

if ($extension)
{
    echo 'HandlerSocket Extension', PHP_EOL;
    echo 'Version: ', (string)$version, PHP_EOL;

    if ($hsclient)
    {
        echo 'Implement: libhsclient', PHP_EOL;
    }
    else
    {
        echo 'Implement: native', PHP_EOL;
    }
}
else
{
    echo 'Disable: HandlerSocket Extension', PHP_EOL;
}
