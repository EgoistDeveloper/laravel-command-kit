<?php

namespace LaravelCommandKit\Commands\MicroApp;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MicroAppCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'micro_app {name} {--table=} {--controller=true} {--use-base-folder=false}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates micro app files';

    protected $defaultTypes = [
        // model
        [
            'type' => 'model',
            'suffix' => '',
            'path' => 'App\Models',
            'stub' => 'app\Console\Commands\MicroApp\Stubs\model.stub',
            'use_base_folder' => false
        ], // repository
        [
            'type' => 'repository',
            'suffix' => 'Repository',
            'path' => 'App\Repositories',
            'stub' => 'app\Console\Commands\MicroApp\Stubs\repository.stub',
            'use_base_folder' => false
        ], // request
        [
            'type' => 'request',
            'suffix' => 'Request',
            'path' => 'App\Http\Requests',
            'stub' => 'app\Console\Commands\MicroApp\Stubs\request.stub',
            'use_base_folder' => true
        ], // controller
        [
            'type' => 'controller',
            'suffix' => 'Controller',
            'path' => 'App\Http\Controllers',
            'stub' => 'app\Console\Commands\MicroApp\Stubs\controller.stub',
            'use_base_folder' => true
        ]
    ];

    protected $optionalTypes = [
        'migration' => [
            'type' => 'migration',
            'suffix' => '',
            'path' => 'database\migrations',
            'stub' => 'app\Console\Commands\MicroApp\Stubs\migration.stub'
        ]
    ];

    /**
     * Execute the console command.
     *
     * @return bool|null
     */
    public function handle()
    {
        $argName = $this->argument('name');
        $baseClassName = $argName;
        $namespace = null;

        // extract namespace and class name
        if (strpos($argName, '\\') > -1) {
            $arr = explode('\\', $argName);
            $baseClassName = end($arr);

            $namespace = Str::replaceLast("\\{$baseClassName}", '', $argName);
        }


        $this->info(">>>>>>>>>>>>>>>> Generating Micro App");

        foreach ($this->defaultTypes as $type) {
            $path = "{$type['path']}\\{$namespace}";
            $className = "{$baseClassName}{$type['suffix']}";
            $_namespace = $namespace;

            if ($this->option('use-base-folder') == 'false' && $type['use_base_folder'] === false){
                $result = $this->isStartingWithBaseFolder($namespace, $path);

                if ($result){
                    $path = $result['path'];
                    $_namespace = $result['namespace'];
                }
            }

            $file = [
                'path' => $path,
                'base_class' => $baseClassName,
                'class_name' => $className,
                'file_path' => base_path("{$path}\\{$className}.php"),
                'stub_path' => base_path($type['stub']),
                'namespace' => $_namespace
            ];

            //$this->info(print_r($file));

            // run target script
            $script = "{$type['type']}Script";
            $this->$script($type, $file);
        }

        $this->info(">>>>>>>>>>>>>>>> Process completed");
    }

    private function modelScript($type, $file)
    {
        $argTable = $this->option('table');

        if ($argTable) {
            $replacements = [
                '{{namespace}}' => "{$file['path']}",
                '{{class}}' => $file['class_name'],
                '{{table}}' => $argTable
            ];

            // create model class
            $result = $this->replaceCustomClass($type, $file, $replacements);

            if ($result) {
                // prepare for migration
                $path = $this->optionalTypes['migration']['path'];
                $date = date('Y_m_d_His');

                // Todo: ...
                //$migrationFile = base_path("{$path}\\*_create_{$argTable}.php");

                // check migration file is exists
                if ($this->migrationIsExists($argTable)){
                    $this->warn(">> [migration] {_{$argTable}} already exists. Passed.");
                    return;
                }

                // ask for migration file
                $askMigration = strtolower($this->ask('Do you want to create migration file? (yes/no)'));

                if ($askMigration && ($askMigration == 'yes' || $askMigration == 'y')) {
                    $type = $this->optionalTypes['migration'];

                    $file = [
                        'path' => $path,
                        'class_name' => 'Create'.$this->pluralize($file['class_name']),
                        'file_path' => base_path("{$path}\\{$date}_create_{$argTable}.php"),
                        'stub_path' => base_path($type['stub'])
                    ];

                    $replacements = [
                        '{{class}}' => $file['class_name'],
                        '{{table}}' => $argTable
                    ];

                    // create migration file
                    $this->replaceCustomClass($type, $file, $replacements);
                } else {
                    $this->warn(">> [migration] passed.");
                }
            }
        } else {
            $this->warn(">> [{$type['type']}] option \"--table\" missing for model. Passed.");
        }
    }

    private function repositoryScript($type, $file){
        $modelType =  $this->getType('type');

        $replacements = [
            '{{namespace}}' => "{$file['path']}",
            '{{class}}' => $file['class_name'],
            //'{{model_namespace}}' => "{$modelType['path']}\\{$file['base_class']}",
            '{{model_namespace}}' => "{$modelType['path']}\\{$file['namespace']}\\{$file['base_class']}",
            '{{model_class}}' => $file['base_class']
        ];

        $this->replaceCustomClass($type, $file, $replacements);
    }

    private function requestScript($type, $file){
        $modelType =  $this->getType('type');

        $replacements = [
            '{{namespace}}' => "{$file['path']}",
            '{{class}}' => $file['class_name']
        ];

        $this->replaceCustomClass($type, $file, $replacements);
    }

    private function controllerScript($type, $file){
        $requestType =  $this->getType('request');
        $requestClass = "{$file['base_class']}{$requestType['suffix']}";

        $repositoryType =  $this->getType('repository');
        $repositoryClass = "{$file['base_class']}{$repositoryType['suffix']}";
        $repositoryType['namespace'] = $file['namespace'];

        if ($this->option('use-base-folder') == 'false' && $repositoryType['use_base_folder'] === false){
            $result = $this->isStartingWithBaseFolder($file['namespace'], "{$repositoryType['path']}");

            if ($result){
                $repositoryType['path'] = $result['path'];
                $repositoryType['namespace'] = $result['namespace'];
            }
        }

        $replacements = [
            '{{namespace}}' => "{$file['path']}",
            '{{class}}' => $file['class_name'],

            '{{repository_namespace}}' => "{$repositoryType['path']}\\{$repositoryType['namespace']}\\{$repositoryClass}",
            //'{{repository_namespace}}' => "{$repositoryType['path']}\\{$repositoryClass}",
            '{{repository_class}}' => $repositoryClass,
            '{{repository_class_var}}' => lcfirst($repositoryClass),

            '{{request_namespace}}' => "{$requestType['path']}\\{$file['namespace']}\\{$requestClass}",
            //'{{request_namespace}}' => "{$requestType['path']}\\{$requestClass}",
            '{{request_class}}' => $requestClass,
        ];

        $this->replaceCustomClass($type, $file, $replacements);
    }

    private function replaceCustomClass($type, $file, $replacements)
    {
        // create folder if it is not exists
        if (!File::exists($file['path'])) {
            File::makeDirectory($file['path'], 0775, true, true);
        }

        $filePath = $file['file_path'];

        if (File::exists($filePath)) {
            $this->warn(">> [{$type['type']}] {$file['path']} already exists!");
        } else {
            $stub = File::get($file['stub_path']);

            $stub = str_replace(array_keys($replacements), array_values($replacements), $stub);
            File::put($filePath, $stub);

            $this->info(">> [{$type['type']}] {$file['path']} created successfully.");

            return true;
        }

        return false;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the model class.'],
        ];
    }

    protected function getStub()
    {
        return null;
    }

    /**
     * Pluralizes a word if quantity is not one.
     *
     * @param string $singular Singular form of word
     * @return string Pluralized word if quantity is not one, otherwise singular
     *
     * @source: https://stackoverflow.com/a/16925755/6940144
     */
    private function pluralize($singular)
    {
        $last_letter = strtolower($singular[strlen($singular) - 1]);

        switch ($last_letter) {
            case 'y':
                return substr($singular, 0, -1) . 'ies';
            case 's':
                return $singular . 'es';
            default:
                return $singular . 's';
        }
    }

    private function migrationIsExists($tableName){
        $path = base_path($this->optionalTypes['migration']['path']);
        $files = array_diff(scandir($path), ['.', '..']);

        $fileNames = array_map(function($item){
            $arr = explode(DIRECTORY_SEPARATOR, $item);
            return end($arr);
        }, $files);

        return preg_grep("/^([0-9_]+)_create_({$tableName}).php/", $fileNames) ? true : false;
    }

    private function getType($type){
        $index = array_search($type, array_column($this->defaultTypes, 'type'));

        if (isset($this->defaultTypes[$index])){
            return $this->defaultTypes[$index];
        } else {
            $this->error("Type {$type} missing");
        }

        return false;
    }

    private function isStartingWithBaseFolder($namespace, $path){
        $arr = explode('\\', $namespace);

        if (!$arr){
            $this->error("name not contains folder.");
            return false;
        }

        $baseControllerFolders = $this->getBaseControllerFolders();
        $baseFolder = $arr[0];

        if (!$baseControllerFolders){
            $this->error("There is no any base folder.");
            return false;
        }

        if (in_array($baseFolder, $baseControllerFolders)){
            return [
                'path' => str_replace("{$baseFolder}\\", '', $path),
                'namespace' => str_replace("{$baseFolder}\\", '', $namespace)
            ];
        }

        return false;
    }

    private function getBaseControllerFolders(){
        $controllerType = $this->getType('controller');

        if ($controllerType){
            $folders = glob("{$controllerType['path']}\*", GLOB_ONLYDIR);

            return array_map(function($item) use($controllerType) {
                return trim(str_replace($controllerType['path'], '', $item), '\/\\');
            }, $folders);
        }

        return null;
    }
}
