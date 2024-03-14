<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParametersTableSeeder extends Seeder
{
    public function run()
    {
        $parameters = 
        [
            [
                'id_status' => 9, 
                'ordem' => 1,
                'name' => 'Não optante',
                'message' => 'Você não é optante para fazer o empréstimo do saque-aniversário da Caixa. Por favor, entre no aplicativo e autorize.',
                'timeout_retry' => [1,5,10], 
            ],
            [
                'id_status' => 12, 
                'ordem' => 2,
                'name' => 'É optante, mas não é autorizado pela Facta',
                'message' => 'Você não está autorizado a fazer o empréstimo do saque-aniversário da Caixa pela Facta. Por favor, entre no aplicativo e autorize.',
                'timeout_retry' => [1,5,10], 
            ],
            [
                'id_status' => 13,
                'ordem' => 3,
                'name' => 'É optante e não tem saldo ou está abaixo de 50 reais.',
                'message' => 'Seu saldo é indisponível para saque este mês. Por favor, consulte novamente após o dia 10 do mês seguinte.',
                'timeout_retry' => 0, 
            ],
            [
                'id_status' => 10,
                'ordem' => 4,
                'name' => 'É optante e é aniversariante dentro do mês',
                'message' => 'Você só poderá realizar o saque no próximo mês.',
                'timeout_retry' => 0, 
            ],
            [
                'id_status' => 102,
                'ordem' => 5,
                'name' => 'Erro da caixa',
                'message' => 'As consultas são realizadas a cada 2 segundos.',
                'timeout_retry' => [1,5,10],
            ],
            [
                'id_status' => 5,
                'ordem' => 6,
                'name' => 'É optante e tem contrato em andamento',
                'message' => 'Há um contrato em andamento. Faremos a reanálise após 24 horas.',
                'timeout_retry' => 86400,
            ],
            [
                'id_status' => 7,
                'ordem' => 7,
                'name' => 'É optante e saldo acima de 50 reais',
                'message' => 'Parabéns! Aqui está o seu link para assinatura do contrato.',
                'timeout_retry' => 18000,
            ],
        ];

        // Mapeamento expandido de condições para cada parâmetro
        $conditionsMapping = [
                9 => [
                    [
                        'ordem' => 1,
                        'logical_operator' => null,
                        'variable' => 'optante',
                        'operator' => '==',
                        'value' => false,
                    ],
                ],
                12 => [
                    [
                        'ordem' => 2,
                        'logical_operator' => null,
                        'variable' => 'optante',
                        'operator' => '==',
                        'value' => true,
                    ],
                    [
                        'ordem' => 3,
                        'logical_operator' => '&&',
                        'variable' => 'permitido',
                        'operator' => '!=',
                        'value' => 'SIM',
                        'custom_fields' => json_encode(['action_required' => 'Authorize in app']),
                    ],
                ],
                13 => [
                    [
                        'ordem' => 4,
                        'logical_operator' => null,
                        'variable' => 'optante',
                        'operator' => '==',
                        'value' => true,
                    ],
                    [
                        'ordem' => 5,
                        'logical_operator' => '&&',
                        'variable' => 'saldo_total',
                        'operator' => '<=',
                        'value' => 50.00,
                    ],
                ],
                10 => [
                    [
                        'ordem' => 6,
                        'logical_operator' => null,
                        'variable' => 'optante',
                        'operator' => '==',
                        'value' => true,
                    ],
                    [
                        'ordem' => 7,
                        'logical_operator' => '&&',
                        'variable' => 'aniversariante',
                        'operator' => '==',
                        'value' => true, // Esta lógica deve ser ajustada conforme a implementação real
                    ],
                ],
                5 => [
                    [
                        'ordem' => 8,
                        'logical_operator' => null,
                        'variable' => 'optante',
                        'operator' => '==',
                        'value' => true,
                    ],
                    [
                        'ordem' => 9,
                        'logical_operator' => '&&',
                        'variable' => 'contrato_vigente',
                        'operator' => '==',
                        'value' => true,
                    ],
                ],
                102 => [
                    [
                        'ordem' => 10,
                        'logical_operator' => null,
                        'variable' => '(strpos(\'erro aqui\', \'erro\') !== false)',
                        'operator' => '==',
                        'value' => '1', // Esta condição precisa ser adaptada ao contexto do código
                    ],
                ],
                7 => [
                    [
                        'ordem' => 11,
                        'logical_operator' => null,
                        'variable' => 'optante',
                        'operator' => '==',
                        'value' => true,
                    ],
                    [
                        'ordem' => 12,
                        'logical_operator' => '&&',
                        'variable' => 'saldo_total',
                        'operator' => '>',
                        'value' => 50.00,
                    ],
                    [
                        'ordem' => 12,
                        'logical_operator' => '&&',
                        'variable' => 'permitido',
                        'operator' => '==',
                        'value' => 'SIM',
                    ],
                ],
            ];

            DB::table('condition')->delete();
            DB::table('parameters')->delete();

            foreach ($parameters as $parameter) {
                // Insere cada parâmetro e obtém seu ID
                $idParameter = DB::table('parameters')->insertGetId([
                    'id_status' => $parameter['id_status'],
                    'ordem' => $parameter['ordem'],
                    'name' => $parameter['name'],
                    'message' => $parameter['message'],
                    'timeout_retry' => is_array($parameter['timeout_retry']) ? json_encode($parameter['timeout_retry']) : $parameter['timeout_retry'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
    
                // Verifica se existem condições mapeadas para este parâmetro
                if (array_key_exists($parameter['id_status'], $conditionsMapping)) {
                    foreach ($conditionsMapping[$parameter['id_status']] as $condition) {
                        DB::table('condition')->insert([
                            'id_parameter' => $idParameter,
                            'ordem' => $condition['ordem'],
                            'logical_operator' => $condition['logical_operator'],
                            'variable' => $condition['variable'],
                            'operator' => $condition['operator'],
                            'value' => $condition['value'],
                            'custom_fields' => isset($condition['custom_fields']) ? $condition['custom_fields'] : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

             // Mapeamento entre termos-chave no nome e valores de flag_process
        $flagProcessMapping = [
            'Não optante' => 0,
            'É optante, mas não é autorizado pela Facta' => 0,
            'Erro da caixa' => 5,
            'É optante e não tem saldo ou está abaixo de 50 reais.' => 2,
            'É optante e é aniversariante dentro do mês' => 2,
            'É optante e tem contrato em andamento' => 0,
            'É optante e saldo acima de 50 reais' => 2,
        ];

        $parameters = DB::table('parameters')->get();

        foreach ($parameters as $parameter) {
            // Determina o valor de flag_process baseado no mapeamento
            $flagProcess = 0; // Valor padrão para casos não mapeados
            foreach ($flagProcessMapping as $key => $value) {
                if (strpos($parameter->name, $key) !== false) {
                    $flagProcess = $value;
                    break; // Para o loop assim que encontrar um match
                }
            }

            $json = json_encode([
                        "id" => "action_database",
                        "type" => "send_request",
                        "config" => [
                            "method" => "POST",
                            "url" => "http://localhost/update_table",
                            "body" => [
                                "table" => "queue",
                                "id" => '{id_queue}',
                                "data" => [
                                    "flag_process" => $flagProcess,
                                    "response" => "{response}",
                                    "response_status" => "{status}",
                                    "message" => "{{ column:message }}"
                                ]
                            ]
                        ]
            ]);

            DB::table('parameters')->where('id', $parameter->id)->update(['action' => $json]);
        }
    }
}