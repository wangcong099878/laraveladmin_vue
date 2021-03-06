<?php

namespace App\Console\DevelopCommands;


use App\Models\Migration;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:all {table : The name of model}
    {--connection} {module?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成资源页面';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $table = $this->argument('table');

        //生成迁移
        $migration = Migration::where('migration','like','%create_'.$table.'_table')->value('migration');
        if($migration &&
            !$this->confirm('迁移文件已经存在,是否继续生成? [y|N]')){
            $this->info($migration.'文件已经存在!');
        }else{
            $this->call('migrate:generate',[
                '--tables'=>$table,
                '--ignore'=>true,
                '--path'=>database_path('migrations/'.date('Y'))
            ]);
            if($migration){
                Migration::where('migration',$migration)->delete();
                Storage::disk('migrations')->delete(substr($migration,0,4).'/'.$migration.'.php');
            }
        }

        //生成模型
        $this->call('create:model',[
            'table'=>$table,
            '--no_dump'=>true
        ]);

        $module = $this->argument('module')?:'admin';
        $modelName = Str::studly($module).'/'.Str::studly(Str::singular($table));
        //生成控制器
        $this->call('create:controller',[
            'name'=>$modelName,
            '--no_dump'=>true
        ]);
        //生成列表视图
        $this->call('create:view',[
            'controller'=>$modelName,
            'template'=>'index'
        ]);
        //生成编辑视图
        $this->call('create:view',[
            'controller'=>$modelName,
            'template'=>'edit'
        ]);
        app('composer')->dumpAutoloads(); //自动加载文件
    }
}
