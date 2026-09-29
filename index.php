<?php

if (php_sapi_name() !== 'cli') {
    exit("Run from CLI only.\n");
}

function is_true($val, $return_null=false){
    $boolval = ( is_string($val) ? filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : (bool) $val );
    return ( $boolval===null && !$return_null ? false : $boolval );
}

define('PHP_TAB', "\t");
define('CLI_RESET', "\033[0m");
define('CLI_BOLD', "\033[1m");
define('CLI_CLREOL', "\033[K");

define('CLI_BLACK', "\033[30m");
define('CLI_RED', "\033[31m");
define('CLI_GREEN', "\033[32m");
define('CLI_YELLOW', "\033[33m");
define('CLI_BLUE', "\033[34m");
define('CLI_MAGENTA', "\033[35m");
define('CLI_CYAN', "\033[36m");
define('CLI_WHITE', "\033[37m");

define('CLI_BLACKBG', "\033[40m");
define('CLI_REDBG', "\033[41m");
define('CLI_GREENBG', "\033[42m");
define('CLI_YELLOWBG', "\033[43m");
define('CLI_BLUEBG', "\033[44m");
define('CLI_MAGENTABG', "\033[45m");
define('CLI_CYANBG', "\033[46m");
define('CLI_WHITEBG', "\033[47m");

define('CLI_ERROR', "\033[41;30m" . CLI_CLREOL);
define('CLI_WARNING', "\033[43;30m" . CLI_CLREOL);
define('CLI_INFO', "\033[44;30m" . CLI_CLREOL);
define('CLI_SUCCESS', "\033[42;30m" . CLI_CLREOL);

function message($text, $status) {
    $out = '';
    switch($status) {
        case 'SUCCESS':
            $out = CLI_SUCCESS.' SUCCESS: '.chr(27).'[0;32m '; //Green background
            break;
        case 'ERROR':
            $out = CLI_ERROR.' ERROR: '. chr(27).'[0;31m '; //Red
            break;
        case 'WARNING':
            $out = CLI_WARNING.' WARNING: '; //Yellow background
            break;
        case 'INFO':
            $out = CLI_INFO.' INFO: '. chr(27).'[0;34m '; //Blue
            break;
        case 'STDOUT':
            $out = CLI_WHITE.' '; 
            break;
        case 'HEADER':
            $out = CLI_CYANBG.' ';             
            break;
        case 'HELP':
            $out = CLI_GREENBG.' HELP: '. chr(27).'[0;32m '; //Green
            break;
        default:
            throw new Exception('Invalid status: ' . $status);
    }
    if ($status === 'STDOUT') {
        return "{$out}{$text}".CLI_RESET;
    } else {
        return "\n{$out}{$text}".CLI_RESET."\n\n";
    }
}

function parse_args($args) {
    $defaults = array(
        'find' => '',  // The string to find
        'replace' => '', // The string to replace it with
        'path' => __DIR__, // The directory to search in (defaults to execution directory)
        'apply' => false,
        'backup' => false,
        'dry-run' => false,
        'help' => false,
    );
    foreach($args as $a) {
        if (substr($a,0,2) == '--') {
            if ($equals_sign = strpos($a,'=',2)) {
                $key = substr($a, 2, $equals_sign-2);
                $val = substr($a, $equals_sign+1);
                $defaults[$key] = $val;
            }
            else {
                $flag = substr($a, 2);
                $defaults[$flag] = true;
            }
        }
    }   
    
    return $defaults;
}

function show_header() {
    echo CLI_CYAN,PHP_TAB . "        __________ _________ ___    __  _________          _____             ", CLI_RESET . PHP_EOL;
    echo CLI_CYAN,PHP_TAB . "       /   /_____/ \__/ \__/|   \  |  ||     O   \        \ ~  /_            ", CLI_RESET . PHP_EOL;
    echo CLI_CYAN,PHP_TAB . "       \___\%%%%%' _`%\_/%'_|____\_|__||_________/         >&&<.-'           ", CLI_RESET . PHP_EOL;
    echo CLI_CYAN,PHP_TAB . "        `BB'       `BBBBBBB' `BBBBBBB'  `BBBBBBB'         `BBBBL.=`          ", CLI_RESET . PHP_EOL;
    echo CLI_CYAN,PHP_TAB . " _________  __________ _________  ____        _______   __________ __________", CLI_RESET . PHP_EOL;
    echo CLI_CYAN,PHP_TAB . "|    _o___)/   /_____/|    _o___)/   /_____  /   O   \ /   /_____//   /_____/", CLI_RESET . PHP_EOL;
    echo CLI_CYAN,PHP_TAB . "|___|\____\\\\___\%%%%%'|___|%%%%%'\___\_____\/___/%\___\\\\___\%%%%%'\___\%%%%%'", CLI_RESET . PHP_EOL;
    echo CLI_CYAN,PHP_TAB . " `BB' `BBB' `BBBBBBBB' `B'        `BBBBBBBB'`BB'   `BB' `BBBBBBBB' `BBBBBBBB'", CLI_RESET . PHP_EOL;
    echo PHP_EOL;
}

function show_help() {
    
    show_header();

    print message(basename(__FILE__),'HELP');
    print "Command line utility that will recursively scan a directory and does a full 
namespace-aware/class-name-only replacement (AST-safe replacement using token parsing). 

This will safely handle:
- ClassName
- \ClassName
- Foo\ClassName
- \Vendor\Package\ClassName
- use Foo\ClassName;
- extends Foo\ClassName
- new Foo\ClassName() 
".message('PARAMETERS:','HEADER')."
 --path     The directory to search in (default:__DIR__)
 --find     The string to find (required)
 --replace  The string to replace it with (required)
 --dry-run  Only show any replacement changes (default behavior)
 --apply    Commit any replacement changes
 --backup   Backup any files before commiting changes (optional)
 --help     Displays this help message.
".message('USAGE:','HEADER')."Replace MyClass with NewClass:
    php ".basenam