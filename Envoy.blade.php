<?php
//读取env配置
$env = [];
array_map(function ($item)use (&$env){
    $item = explode('=',$item);
    $value = isset($item[1])?$item[1]:'';
    if($value=='false'){
        $value = '';
    }
    $env[$item[0]] = str_replace(['"',"'"],'',$value);
},explode("\n",file_get_contents('.env')));
$is_online = isset($env['APP_ENV']) && $env['APP_ENV'] && $env['APP_ENV']!='local';
$composer_install = false;
$cache_file = './storage/app/envoy/cache.json';
$cache = ['composer.json'=>''];
if(file_exists($cache_file)){
    $cache = json_decode(file_get_contents($cache_file),true)?:[];
}
$md5_composer = md5(file_get_contents('./composer.json'));
if($cache['composer.json']!=$md5_composer){
    $composer_install = true;
    $cache['composer.json'] = $md5_composer;
}
file_put_contents($cache_file,json_encode($cache));
$path = (isset($path) && $path) ? $path : 'laraveladmin';
$path_dir = '/var/www/laravel/'.$path;
$branch = (isset($branch) && $branch) ? $branch : ($is_online?'master':'dev');
$host = (isset($host) && $host) ? explode(',',$host):[];
$hosts = [];
array_map(function ($value)use(&$hosts){
    if(!$value){
        return ;
    }
    $item = explode('|',$value);
    if(count($item)==2){
        $hosts[$item[0]] = $item[1];
    }
},(isset($env['HOSTS']) && $env['HOSTS'])?explode(';',$env['HOSTS']):[]);
$hosts['local'] = '127.0.0.1';

?>
@servers($hosts)

@task('update', ['on' => ['local']])
cd {{$path_dir}} && \
php {{$path_dir}}/artisan down --redirect="/503" --retry=60 && \
git pull origin {{$branch}} && \
@if($composer_install)
composer install --optimize-autoloader @if($is_online) --no-dev @endif && \
@endif
@if($is_online)
    php artisan optimize
@endif
php artisan migrate:all && \
php artisan db:seed --class=VersionSeeder --force && \
runuser -l www-data -s /bin/sh -c ' cd {{$path_dir}}  && \
@if($is_online && isset($env['SWOOLE_HTTP_HOST']) && $env['SWOOLE_HTTP_HOST'])
    php artisan swoole:http reload && \
@endif
php artisan queue:restart' && \
php artisan build:index.html && \
php artisan up

@endtask

@task('init', ['on' => ['local']])
cd {{$path_dir}} && \
git checkout {{$branch}} && \
git pull origin {{$branch}} && \
chown -R www-data:www-data storage public && \
@if($unzip_vendor)
    unzip -o -d ./ ./vendor.zip && \
@endif
composer install --optimize-autoloader @if($is_online) --no-dev @endif && \
php artisan key:generate --force && \
php artisan storage:link && \
php artisan db:seed --class=CheckDatabaseSeeder --force && \
php artisan migrate:all && \
php artisan db:seed --force && \
@if($is_online)
    php artisan optimize && \
@endif
php artisan build:index.html
@if(!$is_online)
    chmod -R 777 storage bootstrap/cache public
@endif
@endtask

@task('up', ['on' => $host])
cd /var/www/laravel/laraveladmin && \
docker-compose exec php envoy run update --branch={{$branch}} --path={{$path}} && \
docker-compose run --rm -w {{$path_dir}} node npm run prod
@endtask

