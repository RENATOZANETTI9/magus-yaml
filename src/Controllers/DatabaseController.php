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
    public function updateTable(Request $request)
    {
        try {
            $tableName = $request->input('table');
            $recordId = $request->input('id');
            $data = $request->input('data');

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
            $result = DB::table($tableName)->where('id', $recordId)->update($data);
        
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


            // Verifica a constraint antes de inserir
            if (isset($request['condition_constraint'])) {
                $condition = $request->input('condition_constraint');
                if (DB::table($tableName)->whereRaw($condition)->exists()) {
                    return response()->json(['error' => 'A condição da constraint é verdadeira. Não é possível inserir.'], 400);
                }
            }

            // Verifica se a tabela existe
            if (!Schema::hasTable($tableName)) {
                return response()->json(['error' => 'Tabela não encontrada.'], 404);
            }

            // Remove as aspas dos valores numéricos
            foreach ($data as $key => $value) {
                if (is_numeric($value)) {
                    $data[$key] = (int)$value;
                }
            }
            // Busca o registro pelo CPF
            $record = DB::table($tableName)->where('cpf', $data['cpf'])->first();

            if ($record) {
                // Se o registro existir, atualiza
                $result = DB::table($tableName)->where('cpf', $data['cpf'])->update($data);
                $message = 'Registro atualizado com sucesso.';
            } else {
                // adiciona o header caso a coluna exista
                $this->setColumnToData($tableName, 'header', $data, json_encode($request->headers->all()));
                $this->setColumnToData($tableName, 'referer', $data, json_encode(request()->headers->get('referer')));
                $this->setColumnToData($tableName, 'request', $data, json_encode($request->all()));

                // insere o registro
                $result = DB::table($tableName)->insert($data);
                $message = 'Registro criado com sucesso.';
            }

            if ($result) {
                return response()->json(['message' => $message, 'result' => $result]);
            } else {
                return response()->json(['error' => 'Falha ao atualizar/inserir o registro.']);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
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
