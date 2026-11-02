<?php
if(!defined('ABSPATH'))exit;
// Explicit wp-config constants take precedence over the hosting installer.
function hpc_private_base(){
    if(defined('HPC_PRIVATE_DIR'))return HPC_PRIVATE_DIR;
    return (string)get_option('hpc_host_private_dir',defined('HPC_LAB_ROOT')?'/var/hpc-private':'');
}
function hpc_private_dir($suffix=''){
    $base=hpc_private_base();if($base==='')throw new RuntimeException(hpct_text('t04d464a0f2299d4eab20'));
    return rtrim($base,'/').($suffix!==''?'/'.ltrim($suffix,'/'):'');
}
function hpc_template_source(){return defined('HPC_TEMPLATE_SOURCE')?HPC_TEMPLATE_SOURCE:(defined('HPC_LAB_ROOT')?HPC_LAB_ROOT.'/template/application.hwpx':__DIR__.'/resources/application.hwpx');}
function hpc_converter_config(){return [
    'bin'=>defined('HPC_RHWP_BIN')?HPC_RHWP_BIN:hpc_private_dir('runtime/bin/rhwp'),
    'fonts'=>defined('HPC_FONT_DIR')?HPC_FONT_DIR:hpc_private_dir('runtime/fonts'),
    'sans'=>defined('HPC_FONT_SANS')?HPC_FONT_SANS:'Noto Sans CJK KR',
    'serif'=>defined('HPC_FONT_SERIF')?HPC_FONT_SERIF:'Noto Serif CJK KR',
    'mono'=>defined('HPC_FONT_MONO')?HPC_FONT_MONO:'Noto Sans Mono CJK KR'
];}
