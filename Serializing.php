<?php
namespace PYSys\Tools;

use Nette\ComponentModel\IContainer;
use Nette\NotImplementedException;
use Nette\Utils\Callback;
use Nette\Utils\Strings;
use Tracy\Debugger;

class Serializating
{

	public static function serialize($value, $nesting = []) {
	    $serialized = '';

	    if(is_array($value)) {
	        $serialized .= 'a:' . \count($value) . ':{';
	        foreach($value as $k => $v) {
	            $serialized .= self::serialize($k, $nesting) . self::serialize($v, $nesting);
            }
	        $serialized .= '}';
        } else if (is_string($value)) {
            $serialized .= 's:' . strlen($value) . ':"' . $value . '";'; // musi byt strlen, aby odpovidaly delky s puvodnim serialize
        } else if (is_int($value)) {
            $serialized .= 'i:' . $value . ';';
        } else if (is_float($value)) {
            $serialized .= 'd:' . rtrim( number_format($value,46,'.',''), '0.') . ';';
        } else if (is_bool($value)) {
            $serialized .= 'b:' . (int) $value . ';';
        } else if (is_null($value)) {
            $serialized .= 'N;';
        } else if ($value instanceof \Closure) {
            throw new \Exception('Serialization of \'Closure\' is not allowed');
        } else if (is_object($value)) {
            if(in_array($value, $nesting, true)) {
                throw new \Exception('Nested objects are not allowed for serialization');
            }
            $nesting[] = $value;

            $serialized .= 'O:' . strlen(get_class($value)) . ':"' . get_class($value) . '":'; // musi byt strlen, aby odpovidaly delky s puvodnim serialize
            $object_data = '';
            $object_vars_cnt = 0;
            foreach((array) $value as $k => $v) {
                $object_vars_cnt++;
                if (preg_match('#^(\x0\*\x0.+)\z#', $k, $m)) { // protected
                    $k = $m[1];
                } elseif (preg_match('#^(\x0.+)(\x0.+)\z#', $k, $m)) { // private
                    $k = $m[1] . $m[2];
                }
                $object_data .= self::serialize($k, $nesting) . self::serialize($v, $nesting);
            }
            $serialized .= $object_vars_cnt . ':{' . $object_data . '}';
        } else {
	        throw new NotImplementedException('Ojkuu...');
        }

	    return $serialized;
    }

    public static function unserialize(&$value) {
        $data = null;
	    $type = substr($value, 0, 1);
        if ($type === '}') {
            $value = substr($value, 1);
        } else {
            $value = substr($value, 2);
        }
        $length = 0;

	    if($type === 'a') {
            $delimiter_position = strpos($value, ':');
            $length = (int) substr($value, 0, $delimiter_position);
            $value = substr($value, $delimiter_position + 1);

	        $data = [];
	        $value = substr($value, 1); // remove first bracket
            while(($key = self::unserialize($value)) !== null) {
                $data[$key] = self::unserialize($value);
            }
        } elseif ($type === 's') {
            $delimiter_position = strpos($value, ':');
            $length = (int) substr($value, 0, $delimiter_position);
            $value = substr($value, $delimiter_position + 1);

            $data = substr($value, 1, $length);
            $value = substr($value, $length+3); // remove stripped value
        } elseif ($type === 'i') {
            $delimiter_position = strpos($value, ';');
            $data = (int) substr($value, 0, $delimiter_position);
            $value = substr($value, $delimiter_position + 1);
        } elseif ($type === 'd') {
            $delimiter_position = strpos($value, ';');
            $data = (float) substr($value, 0, $delimiter_position);
            $value = substr($value, $delimiter_position + 1);
        } elseif ($type === 'b') {
            $data = (bool) substr($value, 0, 1);
            $value = substr($value, 2);
        } elseif ($type === 'N') {
	        $data = NULL;
        } elseif ($type === 'O') {
            $delimiter_position = strpos($value, ':');
            $length = (int) substr($value, 0, $delimiter_position);
            $value = substr($value, $delimiter_position + 1);

            $object_name = substr($value, 1, $length);
            $value = substr($value, $length+3); // remove stripped value

            $delimiter_position = strpos($value, ':');
            $length = (int) substr($value, 0, $delimiter_position);
            $value = substr($value, $delimiter_position + 1);

            $value = substr($value, 1); // remove first bracket
            $data = [];
            while(($key = self::unserialize($value)) !== null) {
                $data[$key] = self::unserialize($value);
            }
            $data = (object) $data; // todo set class type
        } elseif ($type === '}') {
            $data = null;
        } else {
            throw new NotImplementedException('Ojkuu...');
        }

	    return $data;
    }

}