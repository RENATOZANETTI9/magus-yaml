<?php

namespace Magus\Yaml\Controllers;

use Magus\Yaml\Models\Parameter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class DatabaseController extends Controller
{

    public function getTableData($tableName, $limit, $identify = null, $value = null)
    {
        try {

            // Verifica se a tabela existe
            if (!Schema::hasTable($tableName))
                return response()->json(['error' => 'Tabela não encontrada.'], 404);

            // Obtém os dados do registro(s)
            if($identify==null && $value==null) 
                $records = DB::table($tableName)->limit($limit)->get();
            elseif($identify!=null && $value==null)
                $records = DB::table($tableName)->whereNull($identify)->limit($limit)->get();
            else 
                $records = DB::table($tableName)->where($identify, $value)->limit($limit)->get();

            if ($records->isEmpty()) {
                return response()->json(['error' => 'Registro não encontrado.']);
            } else {
                return response()->json(['message' => 'Registros encontrados.', 'records' => $records]);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }
    
    public function updateTable(Request $request)
    {
        try {
            $tableName = $request->input('table');
            $data = $request->input('data');
            $identify = $request->input('identify');
            $recordId = $request->input($identify);

            // Verifica se a tabela existe
            if (!Schema::hasTable($tableName)) {
                return response()->json(['error' => 'Tabela não encontrada.'], 404);
            }

            // Remove as aspas dos valores numéricos
            foreach ($data as $key => $value) {
                if (is_numeric($value)) {
                    $data[$key] = (int)$value; // Converte para inteiro
                }
            }
            
            // Atualiza o registro
            $result = DB::table($tableName)->where($identify, $recordId)->update($data);
        
            if ($result) {
                return response()->json(['message' => 'Registro atualizado com sucesso.', 'result' => $result]);
            } else {
                return response()->json(['error' => 'Falha ao atualizar o registro.']);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e], 404);
        }
    }

    public function createOrUpdateTable(Request $request)
    {
        try {
            $tableName = $request->input('table');
            $data = $request->input('data');
            $identify = $request->input('identify');
    
            // Verifica se a tabela existe
            if (!Schema::hasTable($tableName)) {
                return response()->json(['error' => 'Tabela não encontrada.'], 404);
            }
    
            foreach ($data[$tableName] as $item) {

                // Remove as aspas dos valores numéricos
                foreach ($item as $key => $value) {
                    if (is_numeric($value)) {
                        $item[$key] = (int) $value;
                    }
                }

                // Converte arrays para JSON
                $item = $this->convertArraysToJson($item);
    
                // Busca o registro pelo identify
                $record = DB::table($tableName)->where($identify, $item[$identify])->first();
    
                if ($record) {
                    // Se o registro existir, atualiza
                    DB::table($tableName)->where($identify, $item[$identify])->update($item);
                } else {
                    // adiciona o header caso a coluna exista
                    $this->setColumnToData($tableName, 'header', $item, json_encode($request->headers->all()));
                    $this->setColumnToData($tableName, 'referer', $item, json_encode(request()->headers->get('referer')));
                    $this->setColumnToData($tableName, 'request', $item, json_encode($request->all()));
    
                    // insere o registro
                    DB::table($tableName)->insert($item);
                }
            }
    
            return response()->json(['message' => 'Registros atualizados/inseridos com sucesso.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }
    
    public function convertArraysToJson($item)
    {
        foreach ($item as $key => $value) {
            if (is_array($value)) {
                // Se o valor for um array, converte para JSON
                $item[$key] = json_encode($this->convertArraysToJson($value));
            }
        }

        return $item;
    }


    public function setColumnToData($tableName, $column, &$data, $value) {
        // Adiciona o header somente se a coluna existir
        if(Schema::hasColumn($tableName, $column)) {
          $data[$column] = $value;
        }
    }

    public function insertTable(Request $request)
    {
        try {
            $tableName = $request->input('table');
            $data = $request->input('data');
            
            // Verifica se a tabela existe
            if (!Schema::hasTable($tableName)) {
                return response()->json(['error' => 'Tabela não encontrada.'], 404);
            }
    
            // Verifica a constraint antes de inserir
            if (isset($request['condition_constraint'])) {
                $condition = $request->input('condition_constraint');
                if (DB::table($tableName)->whereRaw($condition)->exists()) {
                    return response()->json(['error' => 'A condição da constraint é verdadeira. Não é possível inserir.'], 400);
                }
            }
    
            // Remove as aspas dos valores numéricos
            foreach ($data as $key => $value) {
                if (is_numeric($value)) {
                    $data[$key] = (int) $value; // Converte para inteiro
                }
            }
    
            // Insere o registro
            $result = DB::table($tableName)->insert($data);
    
            if ($result) {
                return response()->json(['success' => 'Registro inserido com sucesso.', 'data' => $data]);
            } else {
                return response()->json(['error' => 'Falha ao inserir o registro.']);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getParameters()
    {
        // Busca todos os parâmetros e carrega suas condições relacionadas
        $parameters = Parameter::where('active', true) // Filtra os parameters ativos
        ->whereHas('conditions', function ($query) {
            $query->where('active', true); // Filtra apenas conditions ativas
        })
        ->with(['conditions' => function ($query) {
            $query->where('active', true); // Garante que apenas conditions ativas sejam carregadas
        }])->orderBy('ordem')->get();

        // Transforma os dados para o formato desejado
        $formattedData = $parameters->map(function ($parameter) {
            return [
                'id' => $parameter->id,
                'id_status' => $parameter->id_status,
                'name' => $parameter->name,
                'message' => $parameter->message,
                'timeout_retry' => $parameter->timeout_retry,
                'action' => $parameter->action,
                'conditions' => $parameter->conditions->map(function ($condition) {
                    return [
                        'id' => $condition->id,
                        'id_parameter' => $condition->id_parameter,
                        'logical_operator' => $condition->logical_operator,
                        'variable' => $condition->variable,
                        'operator' => $condition->operator,
                        'value' => $condition->value,
                        'custom_fields' => $condition->custom_fields,
                    ];
                }),
            ];
        });

        return response()->json($formattedData);
    }
}
