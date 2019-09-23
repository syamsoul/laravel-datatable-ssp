<?php

if (!function_exists('isNotLumen')) {
    function isNotLumen() : bool
    {
        return ! preg_match('/lumen/i', app()->version());
    }
}

if (!function_exists('sd_str_replace_nth')) {
    function sd_str_replace_nth($search, $replace, $subject, $nth=null): array
    {
        $matches = [];
        
        $found = preg_match_all('/'.preg_quote($search).'/i', $subject, $matches, PREG_OFFSET_CAPTURE);
        if (false !== $found && $found > $nth) {
            $matches = $matches[0];
            
            if($nth==null) $keys = range(0, count($matches)-1); 
            else $keys = [$nth];
            
            foreach($keys as $key){
                if(is_callable($replace)) $replacement = $replace($matches[$key][0]);
                else $replacement = $replace;
                
                $search_len = strlen($search);
                $replac_len = strlen($replacement);
                $diff_len   = $replac_len - $search_len;
                
                $subject = substr_replace($subject, $replacement, $matches[$key][1]+($diff_len*$key), $search_len);
            }
        }else{
            $matches = [];
        }
        return [
            $subject,
            count($matches)
        ];
    }
}

if (!function_exists('sd_get_array_last')) {
    function sd_get_array_last(array $arr)
    {
        return $arr[count($arr) - 1];
    }
}