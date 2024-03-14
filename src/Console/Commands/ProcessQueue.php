<?php

namespace Magus\Yaml\Console\Commands;

use Magus\Yaml\Models\Queue;
use Magus\Yaml\Services\ApiService;
use ErrorException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Symfony\Component\Yaml\Yaml;
class ProcessQueue extends Command
{
    protected $signature = 'process:queue';
    protected $description = 'Processa itens na fila de processamento';

    private $response = [];
    private $extensionFile = '.yaml';
    public static $variables = [];
    private static $parameters = [];

    public static $config;
    
    public static $configName;

    public static $workflowName;
    
    public static $workflow;
    public static $conditions;

    public static $currentEndpoint;

    public $actionList = [];

    public static $processedConditions = [];

    public function __construct()
    {
        parent::__construct();
    }
    
    public function handle()
    {
        self::$processedConditions = ProcessQueue::getConditions();
        // NÃO ESQUECER DE DECOMENTAR ***************************************************************************
        // Queue::where('flag_process', 0)
        //     ->orWhere(function ($query) {
        //         $query->where('flag_process', 1)
        //             ->where('updated_at', '<=', Carbon::now()->subMinute());
        //     })->each(function ($queueItem) {
            Queue::each(function ($queueItem) {
                // seta a variável id_queue com o valor do id da queue atual
                self::setVariable('id_queue', $queueItem->id);

                // pega todas as keys do valor da coluna request e cria variáveis
                $request = json_decode($queueItem->request, true);
                $request = $request['data']['request'];

                // pega todas as keys do valor da coluna request e cria variáveis
                self::parseArrayToVariables($request);

                // pega o nome do arquivo de configuração da fila do banco, exemplo: facta
                self::$configName = $request['config'];

                // renderiza as views
                self::render();

                // executa todas as ações do workflow
                $this->executeActions(self::$workflow['workflow']);

                echo "---------------------------------------------------------------------------------------------------------------------------".PHP_EOL;
                echo "Processamento do CPF:{$request['data']['cpf']} concluído com sucesso.".PHP_EOL;
                echo "---------------------------------------------------------------------------------------------------------------------------".PHP_EOL;
                // dump('sucessoooooooooo');
                // dd(self::var('codigo'));
                // dd(self::$variables);
            });
    }

     // verificar recursivamente se o array possui uma key chamada include, caso existir executar a renderização desses arquivos
     protected static function renderArrayRecursively($array) {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $array[$key] = self::renderArrayRecursively($value);
            } elseif ($value === 'include' && isset($array['response'])) {
                foreach ($array['response'] as $responseKey => $responseValue) {
                    if (is_array($responseValue)) {
                        foreach ($responseValue as $subKey => $subValue) {
                            if ($subKey === 'response_test' && isset($subValue['config']['view'])) {
                                $view = $subValue['config']['view'];
                                $rendered = self::renderYaml($view); // Renderiza o conteúdo
                                $array['response'][$responseKey][$subKey]['config']['view'] = $rendered; // Substitui o conteúdo renderizado
                            }
                        }
                    }
                }
            }
        }
        return $array;
    }

    // função para fazer include de arquivos blade dentro de blade
    public static function include($view) {
        dump('Início include-----------------------------------------------------');
        // se for passado um array de arquivos, renderiza cada um deles
        if(is_array($view)) {
            $rendered = '';
            dump('Início include array----------------------------------------------');
            foreach($view as $key => $value) {
                dump('Prsing key => ' . $key);
                $rendered = self::renderYaml($value);
                dump('End Parsing key => ' . $key);
            }
            // dd($rendered);
        } else {
            $rendered = self::renderYaml($view);
        }
        dump('Final include------------------------------------------------------');
       
        // atualiza variáveis
        if(is_array($rendered))
            self::parseArrayToVariables($rendered);

        dump('Final include array----------------------------------------------');
        return $rendered;
        // dump(self::$workflow);
    }

    // renderiza os arquivos yaml
    public static function render() {
        echo 'config => '.self::$configName.PHP_EOL;
        $configRendered = self::renderYaml('config.' . self::$configName);
        self::$config = $configRendered[self::$configName];
        self::$workflow = self::renderYaml('workflow.' . self::$workflowName);
    }

    // verifica se o array possui uma key chamada if
    private function evaluateIf($array) {
        // set $action to ancestor $array['if']
        // $id = isset($array['id']) ?? '';
        // dump("Avaliando condição do action {$id}...");
        if(isset($array['if'])) {
            if(!$array['if'])
                dump('Condição falsa, pulando...');
            return $array['if'];
        }
        return true;
    }

    // A função executeActions itera sobre as ações definidas em then e executa cada ação com base em seu tipo. A execução específica depende dos tipos de ação que você definiu. Aqui está um exemplo de como tratar o tipo send_request:
    protected function executeActions($actions)
    {
        // dd($actions);
        foreach ($actions as $action) {

            try {

                // Se current $action já foi executado não executa
                if(in_array($action, $this->actionList)) {
                    dump('Action ja executada, pulando...');
                    continue;
                }

                // type é obrigatório
                if(!isset($action['type'])) {
                    dump('Type obrigatório, pulando...');
                    continue;
                }

                // id é obrigatório
                if(!isset($action['id'])) {
                    dump('Id obrigatório, pulando...');
                    continue;
                }

                // // avalia condição
                // if(isset($action['config']['if']) && !$this->evaluateIf($action['config'])) 
                //     continue;
            
                echo "---------------------------------------------------------------------------------------------------------------------------".PHP_EOL;
                echo "Type => {$action['type']}".PHP_EOL;
                echo "Processando => {$action['id']}".PHP_EOL;

                // procura pelo array alterado pelo render
                $actionUpdated = $this->findArrayById(self::$workflow, $action['id']);

                // atualiza o antigo array pelo atualizado
                $this->updateArrayRecursively($action, $actionUpdated);

                // renderiza a view para atualizar as viwes
                $this->render();

                dump('switch ($action["type"])');
                switch ($action['type']) {
                    case 'set_variable';
                      
                        //  if($action['id']=='set_variable_optante')
                        //     dd($action['id']);

                         dump('início set_variable');
                         self::setVariable($action['config']['variable'],$action['config']['value']);
                         dump('fim set_variable');
                         break;
                    case 'send_request':
                        
                        echo 'Início send_request ...'.PHP_EOL;
                        // dd($action['config']);
                        dump('início send_request');
                        if(isset($action['config']['endpoint'])) {
                            dump('Início, somente um endpoint encontrado');
                            // evnia o request
                            $this->sendRequest($action);

                            dump('Fim, somente um endpoint encontrado');

                        } elseif(isset($action['config']['endpoints'])) {

                            dump('Início, config.endpoints encontrados');
                            foreach ($action['config']['endpoints'] as $endpoint) {
                                $action['config']['endpoint'] = $endpoint;
                                // dd($action['config']['endpoint']);
                                $this->sendRequest($action);

                            }
                            dump('Fim, config.endpoints encontrados');

                        } else {
                            echo 'Início, somente config encontrado'.PHP_EOL;
                            dump('Início, somente config encontrado');
                            // if($action['id']=='insert_cpf_log') {
                            //     // dump(self::$variables);
                            //     dump('id_status antes de parseVariablesToArray: '.$this->var('id_status'));
                            //     // dd($action);
                            //     $this->parseVariablesToArray($action);
                            //     dump('id_status depois de parseVariablesToArray: '.$this->var('id_status'));
                            //     // dd($action['config']);
                            // }
                            // dd('sucesso');
                            $this->sendRequest($action, false);
                            dump('Fim, somente config encontrado');
                            break;

                        }
                        dump('fim send_request');
                        break; // Garante que apenas este case seja executado
                    case 'condition':
                        dump('inicio condition');
                        // procura pelo array alterado pelo render
                        $actionUpdated = $this->findArrayById(self::$conditions, $action['id']);
                        // atualiza o antigo array pelo atualizado
                        $this->updateArrayRecursively($action, $actionUpdated);

                        // Supõe que `processConditions` avalia se a condição é verdadeira e retorna booleano
                        // dump($action['config']['conditions']);
                        if ($this->processConditions($action['config']['conditions'])) {
                            // Processa ações 'then' se a condição for verdadeira
                            // dump($action['config']['then']);
                            if (isset($action['config']['then'])) 
                                $this->executeActions($action['config']['then']);
    
                            dump('fim condition, condições obedecidas...');
                            return true;
                        }
                        
                        dump('fim condition, condições não obedecidas...');
                        return false;
                    case 'include':
                        // dump($action);
                        dump('inicio include');
                        // if( $action['id']=='include_conditions') {
                        //     dump('sucesso sucesso sucesso sucesso');
                        //     dd($action['config']['view']);
                        // }
                        // dd($action['config']);
                        if(isset($action['config']['view'])) {
                            // dd('possui view');
                             //testes ****
                            // self::setVariable('aniversariante', 'sucesso');
                            // self::setVariable('contrato_vigente', 0);
                            //
                            # pega o conteúdo da view renderizado e cria um item chamado actions
                            // dd('include antes render');
                            dump('include antes render');
                            self::$conditions = self::renderYaml($action['config']['view']);
                            // dd(self::$conditions);
                            // dd($action['config']['actions'] );
                            dump('include depois render');
                            // se é array executa cada item
                            // dd($action['config']['actions'] );
                            // dd('sucesso');
                            if(is_array(self::$conditions)) {
                                // dump(self::$conditions);
                                foreach(self::$conditions as $currentAction) {
                                    $this->executeActions($currentAction);
                                }
                            } else { // caso contrário executa seu conteúdo
                                $this->executeActions(self::$conditions);
                            }
                        }

                        dump('fim include');
                        break;
                    default:
                        // Caso base para tipos de ação desconhecidos ou não manipulados
                        break;
                }

                // // sempre renderizar a view para pegar os valores atualizados
                // $this->render();

                if(isset($action['id'])) {
                    echo "{$action['id']} processado com sucesso...".PHP_EOL;
                    echo "---------------------------------------------------------------------------------------------------------------------------".PHP_EOL;
                }

                // Atualiza a lista de actions executadas
                $this->actionList[] = $action;
            } catch (\Exception $e) {
                // Exibe informações da ação no prompt em caso de erro
                // dump($action);
                echo "Erro: " . $e->getMessage() .  ' line ' . $e->getLine()."\n";
                // dump($actions);
                // Decida se deseja parar a execução ou não
                // break; // Para o loop em caso de erro
                // ou continue; // para continuar apesar do erro
            }
        }
    }

    public function updateArrayRecursively(&$arrayOriginal, $arrayUpdates) {
        if($arrayUpdates == null)
           return;

        foreach ($arrayUpdates as $key => $value) {
            // Se a chave do segundo array existir no primeiro e ambos os valores forem arrays, faça a chamada recursiva
            if (isset($arrayOriginal[$key]) && is_array($value) && is_array($arrayOriginal[$key])) {
                $this->updateArrayRecursively($arrayOriginal[$key], $value);
            } else {
                // Caso contrário, atualize o valor no primeiro array com o valor do segundo
                $arrayOriginal[$key] = $value;
            }
        }
    }

    private function findArrayById($array, $id) {
        // Verifica se o array atual possui a chave 'id' e se ela corresponde ao valor desejado
        if (isset($array['id']) && $array['id'] === $id) {
            return $array; // Retorna o array encontrado
        }
    
        // Se o array contém subarrays, percorre cada um deles recursivamente
        foreach ($array as $element) {
            if (is_array($element)) {
                $result = $this->findArrayById($element, $id);
                if ($result !== null) {
                    return $result; // Retorna o resultado da busca recursiva
                }
            }
        }
    
        return null; // Retorna null se o 'id' não for encontrado
    }

    protected function findFirstActionType($action)
    {
        // Caso base: Se o array atual tem uma chave 'type', retorna o tipo de ação
        if (isset($action['type'])) {
            return $action; // Retorna o array de ação inteiro, incluindo 'type' e quaisquer detalhes de configuração
        }

        // Caso recursivo: Procura em subarrays para encontrar um 'type'
        if(!is_array($action))
            $action = array($action);
        // dump($action);
        foreach ($action as $key => $value) {
            if (is_array($value)) {
                $foundAction = $this->findFirstActionType($value);
                if ($foundAction !== null) {
                    return $foundAction; // Retorna assim que encontrar o primeiro 'type'
                }
            }
        }

        return null; // Retorna null se não encontrar nenhum 'type'
    }

    //A função processConditions receberá a configuração como um array, encontrará a chave "conditions", avaliará a expressão em "if", e se verdadeira, executará o processo definido em "then".
    protected function processConditions($config)
    {
        // dump($config);
        foreach ($config as $condition) {
                //  dd($condition);
                // pega a condição dentro do if
                // $config = $condition['config']['if'];
                // dd($config);
                dump('antes findValueByKey');

                $condResult = $this->findValueByKey($condition, 'if_result');
                // dump($condition['config']['cond_result']);
                // $condResult = $condition['config']['cond_result'];
                dump('$condResult => ' . ($condResult ? 'true' : 'false')) . PHP_EOL;

                dump('depois findValueByKey');
                // dump($condition);
                if($condResult==null) {
                    echo "*****---------------------------------------------------------------------------------------------------------------------------".PHP_EOL;
                    echo "Nenhum if encontrado.".PHP_EOL;
                    echo "*****---------------------------------------------------------------------------------------------------------------------------".PHP_EOL;
                    continue;
                }
                dump('if encontrado...');
                dump($condResult);

                echo "---------------------------------------------------------------------------------------------------------------------------".PHP_EOL;
                $ifText = $condResult ? 'true' : 'false';
                echo "Avaliando condição {$ifText} ...".PHP_EOL;
                if($condResult) {
                    echo  "*** Condição verdadeira. ***".PHP_EOL;
                    // dd($condition['config']['then']);
                    $this->executeActions($condition['config']);
                } else {
                    echo  "*** Condição falsa. ***".PHP_EOL;
                }
                echo "---------------------------------------------------------------------------------------------------------------------------".PHP_EOL;
        }
    }

    // a functio called findValueByKey will receive an array and a key and will return the value of the key in the array or null if the key is not found in the array recursively
    private static function findValueByKey($array, $key) {
        if (isset($array[$key])) {
            return $array[$key];
        }
    
        foreach ($array as $element) {
            if (is_array($element)) {
                $result = self::findValueByKey($element, $key);
                if ($result !== null) {
                    return $result; // Retorna o resultado da busca recursiva
                }
            }
        }
    
        return null; // Retorna null se o 'id' não for encontrado
    }

    // reseta self::$currentEndpoint e renderiza as views
    private function resetCurrentEndpoint() {
        // reseta self::$currentEndpoint pois já foi executado o request
        self::$currentEndpoint = null;
        // renderiza a view para atualizar o valor null
        $this->render();
    }

    // verifica se possui authenticação, faz autenticação e faz o resquest em questão
    private function sendRequest($action, $auth = true) {

        if(isset($action['config']['endpoint'])) {
            self::$currentEndpoint = $action['config']['endpoint'];
            // dump($action['config']['response']);

            // get request details from config
            dump(self::$currentEndpoint);
            $details = $this->findDetailsInConfig('endpoints',self::$currentEndpoint);
        } else
            $details = $action['config'];
        
        // if($action['id']=='create_or_update_cliente') {
        //     dump('sucesso sucesso sucesso sucessoooooo');
        //     dd($action);
        // }
        // dd($details);
        // dd($action);
        
        // se a ação possui um teste de resposta
        dump('antes hasResponseTest');
        if(isset($action['config']['response']['response_test'])) {
            $responseTest = $action['config']['response']['response_test'];

            dump("details[response_test]");
            // atualiza variáveis
            dump('antes hasResponseTest atualizando variáveis');
            $this->parseArrayToVariables($responseTest);

            dump('depois hasResponseTest atualizando variáveis');

            // verifica se existem eventos a executar
            dump('inicio hasResponseTest handleEvents');
            // dd($action);
            $this->handleEvents($action);
            dump('fim hasResponseTest handleEvents');

            // if(self::$currentEndpoint=='simular_valor_liquido')
            //     dd('sucesso simular_valor_liquido');

            // dd(self::$variables);
            // reseta self::$currentEndpoint e executa o render

           

            $this->resetCurrentEndpoint();
            // dd(self::var('saldo_total'));

           
            return;
        } else {
            dump('nao tem response test');
        }
        dump('depois hasResponseTest');

        // se os details vieram de um endpoint do config e prcisa autenticar
        if($auth) {
            $detailsAuth = $this->findDetailsInConfig('endpoints','auth');
            // se a API possue authenticação
            if($detailsAuth != null) {
                $response = ApiService::sendRequest((array)$detailsAuth);
                self::parseArrayToVariables((array)$response);
            }
        }

        // converte pra array
        $details = (array)$details;

        // percorre $details e procura algum value dentro de qualquer key que coincida com alguma key de variables, caso isso ocorra atualiza a key de $details
        $this->parseVariablesToArray($details);

        // dump('sucesso');
        // dump($details);

        $response = ApiService::sendRequest($details);

        // reseta self::$currentEndpoint e executa o render
        $this->resetCurrentEndpoint();

        // atualiza as variáveis com os valores do response
        $this->parseArrayToVariables((array)$response);

        // verifica se existem eventos a executar
        $this->handleEvents($details);

        return $response;
    }

    /**
     * Checks if the response has a test and updates variables if test is true.
     *
     * @param array $config the configuration array
     * @return bool returns true if the response has a test, otherwise false
     */
    // private function getResponseTest($action) {
    //     // dd($config);
    //     // dd($config['response']['response_test']);
    //     $responseTest = $action['config']['response']['response_test'];

    //     dump("details[response_test]");
    //     dd('sucesso');
    //     // atualiza variáveis
    //     dump('antes hasResponseTest atualizando variáveis');
    //     $this->parseArrayToVariables($responseTest);
    //     dump('depois hasResponseTest atualizando variáveis');

    //     // verifica se existem eventos a executar
    //     dump('inicio hasResponseTest handleEvents');
    //     $this->handleEvents($responseTest);
    //     dump('fim hasResponseTest handleEvents');
    //     // dd(self::$variables);

    //     return true;

    //     return false;
    // }

    private function handleEvents($action) {
        dump('antes findValueByKey');
        $status = $this->findValueByKey($action, 'status');
        dump("Response status =>  $status");
        dump('depois findValueByKey');
        // dump(self::$variables);
        // verifica se há eventos a executar
        // dd($action['config']['events']['success']);
        // dd(isset($action['config']['events']) && isset($action['config']['events']['success']) && $status!=null && $status=='200');
        if(isset($action['config']['events']) && isset($action['config']['events']['success']) && $status!=null && $status=='200') {
            // dd('sucesso');
            dump('$this->executeActions($action[config][events][success]);');
            // dd($action['config']['events']['success']);
            $this->executeActions($action['config']['events']['success']);
        }
    }

    private function findDetailsInConfig ($node, $endpointToFind) {
        foreach (self::$config[$node] as $key => $value) {
            if ($key == $endpointToFind) {
                return $value; // Retorna os detalhes do endpoint encontrado
            }
        }
        return null; // Retorna null se o endpoint não for encontrado
    }

    // public function getEndpointDetails($endpointToFind)
    // {
    //     // $collect = collect(self::$config['endpoints'])
    //     //     ->all();
    //     dd(self::$config['endpoints']);
    //     // return $collect->toArray();
    // }
    
    public static function renderYaml($path)
    {
        // $flattenVariables = self::flattenArray(self::$variables);
        // Renderiza o template Blade para uma string
        // dump(self::$variables);
        $yamlContent = View::make($path, self::$variables)->render();
        
        // Processa a string YAML para um array PHP
        $parsedData = Yaml::parse($yamlContent);

        self::dumpComposer();

        // Fazer algo com os dados YAML processados
        // Por exemplo, retornar os dados como uma resposta JSON
        // return response()->json($parsedData);
        return $parsedData;
    }

    public static function dumpComposer() {
        // Verifica se a aplicação está sendo executada no console e se o comando é 'process:queue'
        View::composer(['*'], function ($view) {
            $processedConditions = self::$processedConditions;
            // dump(ProcessQueue::$variables);
            ProcessQueue::replacePlaceholdersWithValues($processedConditions);
            $view->with('ProcessQueue', function() {
                return ProcessQueue::class;
            })->with('var', function($key) {
                return ProcessQueue::var($key);
            })->with('param', function($key) {
                return ProcessQueue::param($key);
            })->with('conditions', function() use ($processedConditions) {
                // Now, use $processedConditions which has already been processed
                return $processedConditions;
            })->with('include', function($key) {
                return ProcessQueue::include($key);
            })->with('currentEndpoint', function() {
                return ProcessQueue::$currentEndpoint;  
            })->with('renderByCondition', function($content, $condition) {
                return ProcessQueue::renderByCondition($content, $condition);  
            });
        });

    }

    public static function renderByCondition($content, $condition): string
    {
        dump('-----------------------------');
        dump('Início renderByCondition');
    
        $cond = $condition ? 'true' : 'false';
        dump("renderByCondition {$cond}");
    
        if ($condition) {
            // Check if $content is a string
            if (is_string($content)) {
                // Check if $content is a valid Blade file
                if (file_exists(resource_path("views/$content.blade.php"))) {
                    return View::make($content, self::$variables)->render();
                } else {
                    // Not a valid Blade file, return $content directly
                    return $content.PHP_EOL;
                }
            } else {
                // Not a string, assume it's not a Blade file and return $content
                return $content.PHP_EOL;
            }
        }
    
        dump('Fim renderByCondition');
        dump('-----------------------------');
    
        return ''; // Change to a more appropriate default value if needed
    }
    
    protected static function flattenArray($array, $prefix = '') {
        $result = [];
    
        foreach ($array as $key => $value) {
            $flattenedKey = $prefix . $key;
    
            if (is_array($value)) {
                $result += self::flattenArray($value, $flattenedKey . '.');
            } else {
                $result[$flattenedKey] = $value;
            }
        }
    
        return $result;
    }
    
    protected static function getParametersFromDB()
    {
        // Decodifica o JSON da coluna 'request' para um array associativo
        $request = [
            'method' => 'GET',
            'url' => 'http://127.0.0.1/parameters',
        ];

        $response = ApiService::sendRequest($request);

        foreach ($response->original as $key => $value) {
            // Define cada chave e valor como uma variável
            self::setParameter($key, $value);
        }
    }

    public static function toArray($string) {
        if($string === 'null') return null;

        $result = json_decode($string, true);
        return $result;
    }

    private static function parseArrayToVariables($array)
    {
        if($array===null) 
            return;

        if(!is_array($array)) 
            return;

        array_walk_recursive($array, function (&$value, $key) {
            $value = str_replace("\x00*\x00", "", $value);
            $key = str_replace("\x00*\x00", "", $key);
            self::setVariable($key, $value);
        });
    }
    
    // percorrer $arraye procura algum value (contains)  dentro de qualquer value da key que coincida com alguma key de variables, caso isso ocorra atualiza o value do $array conforme a $key equivalente e remova {}
    protected static function parseVariablesToArray(&$array)
    {
        array_walk_recursive($array, function (&$value, $key) {
            
            // Verifica se o valor contém uma chave envolvida por '{}'
            if (preg_match('/\{(.+?)\}/', $value, $matches)) {
                // Extrai a chave entre '{}'
                $innerKey = $matches[1];
                // Busca o valor correspondente em $variables
                $found = self::var($innerKey);
                if ($found !== null) {
                    // Atualiza o valor no array com o valor encontrado em $variables
                    // e remove as chaves '{}'
                    $value = str_replace("{" . $innerKey . "}", $found, $value);
                }
            }

            // $value = self::findValueByKey(self::$variables, $key);
        });
    }
    
    function errorHandler($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    // Função para definir uma variável
    private static function setVariable($key, $value)
    {
        self::$variables[$key] = $value;
    }

    // Função para definir um parameter
    private static function setParameter($key, $value)
    {
        self::$parameters[$key] = $value;
    }

    // Função para obter uma variável
    public static function var($key, $returnFirst = true) {
        $found = []; // Armazena os valores encontrados

        // Função para usar com array_walk_recursive
        $checker = function($value, $k) use ($key, &$found) {
            if ($k === $key) {
                $found[] = $value;
            }
        };

        // Aplicar a função de verificação de forma recursiva
        array_walk_recursive(self::$variables, $checker);

        // Retornar com base na opção $returnFirst
        if ($returnFirst) {
            return !empty($found) ? $found[0] : null;
        } else {
            return $found;
        }
    }

    public static function param($key, $returnFirst = true) {
        $found = []; // Armazena os valores encontrados

        // Função para usar com array_walk_recursive
        $checker = function($value, $k) use ($key, &$found) {
            if ($k === $key) {
                $found[] = $value;
            }
        };

        // Aplicar a função de verificação de forma recursiva
        array_walk_recursive(self::$parameters, $checker);

        // Retornar com base na opção $returnFirst
        if ($returnFirst) {
            return !empty($found) ? $found[0] : null;
        } else {
            return $found;
        }
    }

    protected static function getVariableValue($key) {
        $result = null;
    
        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator(self::$variables));
        foreach ($iterator as $k => $v) {
            if ($k === $key) {
                // dump("$k =  $v");
                $result = $v;
                break;
            }
        }

        return $result == null ? $key : $result;
    }

    private static function slashes($value) {
        // Remove as aspas e espaços em branco
        $cleanedValue = trim($value, '"');
    
        // Verifica se o valor é numérico (incluindo decimais)
        if (is_numeric($cleanedValue)) {
            // Converte para inteiro ou mantém o valor decimal
            return is_int($cleanedValue) ? (int)$cleanedValue : (float)$cleanedValue;
        } else {
            // Adiciona aspas ao redor do valor e escapa aspas internas
            return "'" . str_replace("'", "\\'", $cleanedValue) . "'";
        }
    }

    public static function getConditions() {
        // dd(self::$variables);
        // pega todas as keys do valor da coluna request e cria variáveis
        self::getParametersFromDB();
        // *** verificar se o servidor web está iniciado se der erro ***
        
        // dd(self::$parameters);
        $yamlArray = [];
        foreach (self::$parameters as $parameter) {

            // atualiza as variáveis com os valores do registro da tabela parametros
            self::parseArrayToVariables($parameter);

            $ifConditions = [];
            foreach ($parameter['conditions'] as $condition) {
                // Usa a função ajustada para determinar e formatar o valor da variável para 'eval'.
                $variableReplaced = self::getVariableValue($condition['variable']);
                $valueReplaced = self::getVariableValue($condition['value']);
                
                // Formata os valores para 'eval'. 
                $variableFormatted = self::slashes($variableReplaced);
                $valueFormatted = self::slashes($valueReplaced);

                // Cria a expressão para 'eval'.
                $ifConditions[] = "{$variableFormatted} {$condition['operator']} {$valueFormatted}";
                // dump($ifConditions);
            }
            $combinedIfCondition = implode(' && ', $ifConditions);
            $action = json_Decode($parameter['action'], true);  

            // onde tiver column atualiza pelo valor do registro da coluna ex: mensagem
            self::replaceColumnPlaceholdersRecursively($action,$parameter);

            // dd(self::$variables);
            
           // Preparando a expressão para eval
            $evalCode = 'return (' . $combinedIfCondition . ');';
            dump($parameter['name']);
            dump($evalCode);
            try {
                // Avaliando a expressão
                // dump($evalCode);

                $result = eval($evalCode);
                dump($result);
                
                // Se a expressão for avaliada corretamente, você pode usar $result
                $yamlArray[] = [
                    'id' => $parameter['id'],
                    'type' => 'condition',
                    'config' => [
                        'if' => $combinedIfCondition,
                        'if_result' => $result,
                        'then' => $action
                    ],
                ];
            } catch (\ParseError $e) {
                // Tratar erro de sintaxe na expressão
                echo 'Erro na expressão avaliada: ',  $e->getMessage(), "\n";
            }
        }
        // dd($yamlArray);
        return $yamlArray;
    }

    /**
     * Replaces placeholders in the given item with their corresponding values.
     *
     * @param mixed &$item The item to process.
     */
    public static function replacePlaceholdersWithValues(&$item)
    {
        // Iterate recursively over the item and replace placeholders with their values.
        
        // Walk over each element in the given item and replace placeholders with their values.
        array_walk_recursive($item, function (&$value) {
            // Match all occurrences of placeholders in the form of {variable_name} in the value.
            if (preg_match_all('/\{([^}]+)\}/', $value, $matches, PREG_SET_ORDER)) {
                // For each matched placeholder,
                foreach ($matches as $match) {
                    // Extract the placeholder and the variable name.
                    $placeholder = $match[0];
                    $variableName = $match[1];
                    
                    // If the variable name is set in the variables array,
                    if (isset(self::$variables[$variableName])) {
                        // Replace the placeholder with the JSON-encoded value of the variable.
                        $value = str_replace($placeholder, json_encode(self::$variables[$variableName],true), $value);
                        // Escape backslashes in the replaced value.
                        $value = self::slashes($value);
                    }
                }
            }
        });
    }

    protected static function replaceVariableValue($variableValue)
    {
        // Se o valor da variável for uma string, verifica se existe correspondência em $variables.
        if (is_string($variableValue)) {
            // Implementa um iterator recursivo para percorrer todas as chaves e valores em $variables.
            $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator(self::$variables));

            foreach ($iterator as $key => $value) {
                // Verifica se o valor atual é igual à string da variável.
                if ($variableValue === $key) {
                    // Retorna o valor correspondente encontrado.
                    return $value;
                }
            }
        }

        // Retorna o valor original caso não encontre correspondência ou não seja uma string.
        return $variableValue;
    }

    private static function replaceColumnPlaceholdersRecursively(&$action, $parameter) {
        array_walk_recursive($action, function (&$item) use ($parameter) {
            if (is_string($item)) {
                // Encontra todos os placeholders do tipo {{ column:chave }}
                preg_match_all('/\{\{\s*column:\s*([^}]+)\s*\}\}/', $item, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    // $match[0] é o placeholder completo, $match[1] é a chave dentro do placeholder
                    $key = trim($match[1]);
                    if (isset($parameter[$key])) {
                        // Substitui o placeholder pelo valor correspondente de $value
                        $item = str_replace($match[0], $parameter[$key], $item);
                    }
                }
            }
        });
    }
    
    

}
