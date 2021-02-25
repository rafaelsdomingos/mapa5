<?php

namespace Reports;

use DateTime;
use DateInterval;
use MapasCulturais\i;
use League\Csv\Writer;
use MapasCulturais\App;
use MapasCulturais\Traits;
use MapasCulturais\Entities\File;
use MapasCulturais\Entities\Agent;
use MapasCulturais\Entities\Event;
use MapasCulturais\Entities\Space;
use MapasCulturais\Entities\Project;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Registration;

class Controller extends \MapasCulturais\Controller
{

    use Traits\ControllerAPI;
    protected function fetch($sql, $params = [])
    {
        $app = App::i();

        $conn = $app->em->getConnection();

        $conn->fetchAll($sql, $params);
    }

    public function GET_agents()
    {
        $props = ['_type'];
        $meta = ['En_Estado', 'En_Cidade', 'acessibilidade'];

        $daily_data = $this->getEntityDailyData('agent', Agent::class, $props, $meta);
        $total_data = $this->getEntityDailyData('agent', Agent::class, $props, $meta);

        $this->render('agents', ['total_data' => $total_data, 'daily_data' => $daily_data]);
    }
    public function GET_spaces()
    {
        $props = ['_type'];
        $meta = ['En_Estado', 'En_Cidade', 'acessibilidade'];

        $daily_data = $this->getEntityDailyData('space', Space::class, $props, $meta);
        $total_data = $this->getEntityDailyData('space', Space::class, $props, $meta);

        $this->render('spaces', ['total_data' => $total_data, 'daily_data' => $daily_data]);
    }
    public function GET_events()
    {
        $this->entityReport('event', Event::class);
    }
    public function GET_projects()
    {
        $this->entityReport('project', Project::class);
    }
    public function GET_opportunities()
    {
        $this->entityReport('opportunity', Opportunity::class);
    }
    public function GET_files()
    {
        $this->entityReport('file', File::class);
    }
    public function GET_registrations()
    {
        $this->entityReport('registration', Registration::class);
    }

    public function getEntityFilters($entity_class)
    {
        $metadata = $entity_class::getPropertiesMetadata(true);

        $filters = [];
        if (isset($metadata['status'])) {
            $filters[] = 'e.status > 0';
        }

        return $filters;
    }

    /**
     * Gera CSV das inscrições agrupadas por status
     *
     *
     */
    public function GET_exportRegistrationsByStatus()
    {
        $this->requireAuthentication();

        $app = App::i();

        $conn = $app->em->getConnection();

        $request = $this->data;

        $data = [];
        $params = ['opportunity' => $request['opportunity']];

        $query = "SELECT status, count(*) FROM registration r WHERE opportunity_id = :opportunity GROUP BY status";

        $result = $conn->fetchAll($query, $params);

        foreach ($result as $value) {
            switch ($value['status']) {
                case 0:
                    $status = i::__('Rascunho');
                    break;
                case 1:
                    $status = i::__('Pendente');
                    break;
                case 2:
                    $status = i::__('Inválida');
                    break;
                case 3:
                    $status = i::__('Não Selecionada');
                    break;
                case 8:
                    $status = i::__('Suplente');
                    break;
                case 10:
                    $status = i::__('Selecionada');
                    break;
            }

            $data[$status] = $value['count'];
        }

        $csv_data = [];
        foreach ($data as $key => $value) {
            $csv_data[] = [$key, $value];
        }

        $header = [
            i::__('STATUS'),
            i::__('QUANTIDADE'),
        ];

        $this->createCsv($header, $csv_data, $request['action'], $request['opportunity']);

    }

    /**
     * Gera CSV das inscrições agrupadas por avaliação
     *
     *
     */
    public function GET_exportRegistrationsByEvaluation()
    {
        $this->requireAuthentication();

        $app = App::i();

        $conn = $app->em->getConnection();

        $request = $this->data;

        $data = [];
        $params = ['opportunity' => $request['opportunity']];

        $query = "SELECT count(*) AS evaluated FROM registration r WHERE opportunity_id = :opportunity  AND consolidated_result <> '0'";

        $evaluated = $conn->fetchAll($query, $params);

        $query = "SELECT COUNT(*) AS notEvaluated FROM registration r WHERE opportunity_id = :opportunity  AND consolidated_result = '0'";

        $notEvaluated = $conn->fetchAll($query, $params);

        $data = array_merge($evaluated, $notEvaluated);

        foreach ($data as $m) {
            foreach ($m as $v) {
                if (empty($v)) {
                    return false;
                }
            }
        }

        $result = [];
        foreach ($data as $m) {
            foreach ($m as $key => $v) {
                $result[] = [$key, $v];
            }
        }

        $csv_data = [];
        $csv_data = array_map(function ($index) {
            if ($index[0] == "evaluated") {
                return [
                    i::__('AVALIADA'),
                    $index[1],
                ];
            } else {
                return [
                    i::__('NAO AVALIADA'),
                    $index[1],
                ];
            }
        }, $result);

        $header = [
            i::__('STATUS'),
            i::__('QUANTIDADE'),
        ];

        $this->createCsv($header, $csv_data, $request['action'], $request['opportunity']);

    }

    /**
     * Gera CSV das inscrições agrupadas por status da avaliação
     *
     *
     */
    public function GET_exportRegistrationsByEvaluationStatus()
    {
        $opp = $this->getOpportunity();

        $app = App::i();

        $request = $this->data;

        $em = $opp->getEvaluationMethod();

        $conn = $app->em->getConnection();

        $data = [];
        $params = ['opportunity' => $opp->id];

        $query = "SELECT COUNT(*), consolidated_result FROM registration r WHERE opportunity_id = :opportunity  AND consolidated_result <> '0' GROUP BY consolidated_result";

        $evaluations = $conn->fetchAll($query, $params);

        $cont = 0;
        foreach ($evaluations as $evaluation) {
            if ($cont < 8) {
                $data[$em->valueToString($evaluation['consolidated_result'])] = $evaluation['count'];
                $cont++;
            }
        }

        $csv_data = [];
        foreach ($data as $key => $value) {
            $csv_data[] = [$key, $value];
        }

        $header = [
            i::__('STATUS'),
            i::__('QUANTIDADE'),
        ];

        $this->createCsv($header, $csv_data, $request['action'], $request['opportunity']);
    }

    /**
     * Gera CSV das inscrições  agrupadas pela categoria
     *
     *
     */
    public function GET_exportRegistrationsByCategory()
    {
        $opp = $this->getOpportunity();

        $app = App::i();

        $request = $this->data;

        $conn = $app->em->getConnection();

        $csv_data = [];
        $params = ['opportunity' => $opp->id];

        $query = "select  category, count(category) from registration r where r.status > 0 and r.opportunity_id = :opportunity group by category";

        $csv_data = $conn->fetchAll($query, $params);

        foreach ($csv_data as $value) {
            foreach ($value as $v) {
                if (empty($v)) {
                    return false;
                }
            }
        }

        $header = [
            i::__('CATEGORIA'),
            i::__('QUANTIDADE'),
        ];

        $this->createCsv($header, $csv_data, $request['action'], $request['opportunity']);
    }

    /**
     * Gera CSV das inscrições agrupadas por status
     *
     *
     */
    public function GET_exportRegistrationsDraftVsSent()
    {
        $opp = $this->getOpportunity();

        $request = $this->data;

        $app = App::i();

        $conn = $app->em->getConnection();

        $data = [];
        $params = ['opportunity' => $opp->id];

        $query = "SELECT status, count(*) FROM registration r WHERE opportunity_id = :opportunity GROUP BY status";

        $result = $conn->fetchAll($query, $params);

        foreach ($result as $value) {
            switch ($value['status']) {
                case 0:
                    $status = i::__('Rascunho');
                    break;
                case 1:
                    $status = i::__('Pendente');
                    break;
                case 2:
                    $status = i::__('Inválida');
                    break;
                case 3:
                    $status = i::__('Não Selecionada');
                    break;
                case 8:
                    $status = i::__('Suplente');
                    break;
                case 10:
                    $status = i::__('Selecionada');
                    break;
            }

            $data[$status] = $value['count'];
        }

        $csv_data = [];
        $total = 0;
        foreach ($data as $key => $value) {
            if ($key == "Rascunho") {
                $csv_data[0] = ['Rascunho', $value];
            } else {
                $total = ($total + $value);
                $csv_data[1] = ['Enviadas', $total];
            }

        }

        $header = [
            i::__('STATUS'),
            i::__('QUANTIDADE'),
        ];

        $this->createCsv($header, $csv_data, $request['action'], $request['opportunity']);

    }

    /**
     * Gera CSV das Inscrições VS tempo
     *
     *
     */
    public function GET_registrationsByTime()
    {
        $opp = $this->getOpportunity();

        $app = App::i();

        $conn = $app->em->getConnection();

        $initiated = [];
        $sent = [];
        $params = ['opportunity' => $opp->id];

        $query = "SELECT
        to_char(create_timestamp , 'YYYY-MM-DD') as date,
        count(*) as total
        FROM registration r
        WHERE opportunity_id = :opportunity
        GROUP BY to_char(create_timestamp , 'YYYY-MM-DD')
        ORDER BY date ASC";
        $initiated = $conn->fetchAll($query, $params);

        $query = "SELECT
        to_char(sent_timestamp , 'YYYY-MM-DD') as date,
        count(*) as total
        FROM registration r
        WHERE opportunity_id = :opportunity AND r.status > 0
        GROUP BY to_char(sent_timestamp , 'YYYY-MM-DD')
        ORDER BY date ASC";
        $sent = $conn->fetchAll($query, $params);

        if (!$sent || !$initiated) {
            return false;
        }

        $header = [
            i::__('STATUS'),
            i::__('DATA'),
            i::__('QUANTIDADE'),
        ];

        $result = [];
        $count = 0;
        foreach ($sent as $key => $value) {
            $result[$count]['status'] = i::__('Enviada');
            $result[$count] += $value;

            $count++;
        }

        foreach ($initiated as $key => $value) {
            $result[$count]['status'] = i::__('Iniciada');
            $result[$count] += $value;

            $count++;

        }

        $return = array_map(function ($index) {
            $date = new DateTime($index['date']);
            return [
                'status' => $index['status'],
                'data' => $date->format('d/m/Y'),
                'total' => $index['total'],

            ];
        }, $result);

        $this->createCsv($header, $result, $this->data['action'], $opp->id);

    }

    public function ALL_dataOpportunityReport()
    {
        $this->requireAuthentication();
        $app = App::i();
        $request = $this->data;
        $fieldsUse = [
            "raca",
            "genero",
            "orientacaoSexual",
            "En_Estado",
            "En_Municipio",
            "En_Bairro",
            "dataDeNascimento",
        ];
        $opp = $app->repo("Opportunity")->find($request["opportunity"]);
        $dataOpportunity = $opp->getEvaluationCommittee();
        $oppSelectFields = [];
        foreach ($opp->registrationFieldConfigurations as $value) {
            if ($value->fieldType == "select") {
                $oppSelectFields[] = [
                    "label" => $value->title,
                    "value" => $value->fieldName,
                    "source" => "registration_meta",
                ];
            }
        }
        $agentClass = Agent::getClassName();
        $agentFields = $this->getEntitySelectFields($agentClass, "agent",
                                                    $fieldsUse);
        $collectiveFields = (($opp->useAgentRelationColetivo ?? "dontUse") !=
                             "dontUse") ?
                            $this->getEntitySelectFields($agentClass,
                                                         "agent") : [];
        $instFields = (($opp->useAgentRelationInstituicao ?? "dontUse") !=
                       "dontUse") ?
                      $this->getEntitySelectFields($agentClass, "agent") : [];
        $spaceFields = (($opp->useSpaceRelationIntituicao ?? "dontUse") !=
                        "dontUse") ?
                       $this->getEntitySelectFields(Space::getClassName(),
                                                    "space") : [];
        $return = [
            "opportunityType" => $dataOpportunity[0]->owner->type->id,
            "categories" => (is_array($opp->registrationCategories) ?
                             $opp->registrationCategories : []),
            "registrationFields" => $oppSelectFields,
            "agentFields" => $agentFields,
            "collectiveFields" => $collectiveFields,
            "institutionFields" => $instFields,
            "spaceFields" => $spaceFields,
        ];
        $this->apiResponse($return);
    }

    public function POST_createGrafic()
    {
        $opp = $this->getOpportunity();
        $app = App::i();
        $request = $this->data;
        $reportData = $request["reportData"];
        $conn = $app->em->getConnection();

        $params = [
            "opportunity" => $opp->id,
            "field" => $reportData["typeData"],
        ];

        $regWhere = "r.status > 0 AND r.opportunity_id = :opportunity";
        $regMetaSubQuery = "(SELECT object_id, value FROM registration_meta WHERE key = :field)";
        $agentMetaSubQuery = "(SELECT object_id, value FROM agent_meta WHERE key = :field)";
        $selMeta = "SELECT value, count(*) AS quantity FROM registration r";
        $groupMeta = "GROUP BY value";
        $sqls = [
            "registration" => "SELECT :field, count(:field) AS quantity FROM registration r WHERE $regWhere GROUP BY :field",
            "registration_meta" => "$selMeta LEFT OUTER JOIN $regMetaSubQuery AS m ON r.id = m.object_id WHERE $regWhere $groupMeta",
            "agent_meta" => "$selMeta JOIN agent a ON r.agent_id = a.id LEFT OUTER JOIN $agentMetaSubQuery m ON a.id = m.object_id WHERE $regWhere $groupMeta",
        ];

        $query = $sqls[$reportData["source"]];

        $result = $conn->fetchAll($query, $params);

        $return = [];
        $labels = [];
        $color = [];
        $data = [];

        foreach ($result as $key => $value){
            $color[] = $this->color();
            $labels[] = $value['category'];
            $data[] = $value['quantity'];
        }

        $return = [
            'labels' => $labels,
            'backgroundColor' => $color,
            'borderWidth' => 0,
            'data' => $data,
            'typeGrafic' => $reportData['type'],
            'period' => $this->getPeriod($opp->createTimestamp)
        ];

        $this->apiResponse($return);
    }

    private function getEntitySelectFields($entityClass, $baseName,
                                           $includeFields=null)
    {
        $selectFields = [];
        $fields = $entityClass::getPropertiesMetadata();
        foreach ($fields as $key => $value) {
            if ((($value["type"] ?? "") == "select") &&
                (!$includeFields || in_array($key, $includeFields))) {
                $selectFields[] = [
                    "label" => $value["label"],
                    "value" => $key,
                    "source" => ($baseName . ($value["isMetadata"] ?
                                              "_meta" : "")),
                ];
            }
        }
        return $selectFields;
    }

    private function getPeriod($dateStart)
    {
        $period = new \DatePeriod(
            $dateStart,
            new \DateInterval('P1D'),
            new \DateTime()
        );

        $return = [];
        
        foreach ($period as $recurrence) {
            $return[] =  $recurrence->format('Y-m-d');
        }
        
        return $return;
       
    }

    private function getRegistrationIds()
    {
        $opp = $this->getOpportunity();

        $app = App::i();

        $conn = $app->em->getConnection();

        $params = ['opportunity' => $opp->id];

        $query = "SELECT r.id FROM registration r
        JOIN agent a ON r.agent_id  = a.id
        JOIN opportunity o ON r.opportunity_id = o.id
        WHERE r.opportunity_id = :opportunity";
        return $conn->fetchAll($query, $params);
    }

    /**
     *Retorna a opportunidade
     *
     * @return object
     */
    private function getOpportunity(): Opportunity
    {
        $this->requireAuthentication();

        $app = App::i();

        $request = $this->data;

        $opp = $app->repo("Opportunity")->find($request['opportunity']);

        return $opp;

    }

    /**
     * Gera o CSV
     *
     * @param array $header
     * @param array $csv_daa
     */
    private function createCsv($header, $csv_data, $action, $opp)
    {
        $date = new DateTime();
        $fileName = $date->format('dmY') . "-" . $action . "-opp-" . $opp . "-" . md5(json_encode($csv_data)) . ".csv";
        $csv = Writer::createFromString();

        $csv->setDelimiter(';');

        $csv->insertOne($header);

        foreach ($csv_data as $csv_line) {
            $csv->insertOne($csv_line);
        }

        $csv->output($fileName);
    }

    public function getEntityDailyData(string $table, string $entity_class, array $entity_fields = [], array $metadata = [])
    {
        $conn = App::i()->em->getConnection();

        $data = [];

        $metadata = $entity_class::getPropertiesMetadata(true);

        $filters = $this->getEntityFilters($entity_class);

        $where = implode(' AND ', $filters);

        $data['total'] = (object) [
            'label' => 'Total',
            'sql' => "SELECT count(e.*) as num, 'total' as data_group, create_timestamp::DATE as day FROM {$table} e WHERE 1=1 AND ($where) group by data_group, day order by day, data_group ASC",
        ];

        foreach ($entity_fields as $field) {
            $column = $metadata[$field]['columnName'];
            $data[$field] = (object) [
                'label' => $metadata[$field]['label'],
                'sql' => "SELECT count(e.*) as num, e.{$column} as data_group, create_timestamp::DATE as day FROM {$table} e WHERE 1=1 AND ($where) group by data_group, day order by day, data_group ASC",
            ];
        }

        $result = [];
        foreach ($data as $prop => &$q) {
            $rs = $conn->fetchAll($q->sql);

            $mapped = array_map(function ($row) use ($q, $entity_class, $prop) {
                $row = (object) $row;
                $row->data_group = $this->getFieldValueString($entity_class, $prop, $row->data_group);
                $row->field = $prop;
                $row->field_label = $prop;
                return $row;
            }, $rs);

            $result = array_merge($result, $mapped);
        }
        return $result;
    }

    public function getEntityTotalData(string $table, string $entity_class, array $entity_fields = [], array $metadata = [])
    {
        $conn = App::i()->em->getConnection();

        $metadata = $entity_class::getPropertiesMetadata(true);

        $data = [];

        $filters = $this->getEntityFilters($entity_class);

        $where = implode(' AND ', $filters);

        $data['total'] = (object) [
            'label' => 'Total',
            'sql' => "SELECT count(e.*) as num, 'total' as data_group FROM {$table} e WHERE 1=1 AND ($where)",
        ];

        foreach ($entity_fields as $field) {
            $column = $metadata[$field]['columnName'];
            $data[$field] = (object) [
                'field' => $field,
                'label' => $metadata[$field]['label'],
                'sql' => "SELECT count(e.*) as num, e.{$column} as data_group FROM {$table} e WHERE 1=1 AND ($where) group by data_group",
            ];
        }

        foreach ($data as &$q) {
            $q->data = $conn->fetchAll($q->sql);
        }

        return $data;
    }

    public function getFieldValueString($class_name, $field, $value)
    {
        $app = App::i();

        $result = '';

        switch ($field) {
            case '_type':
                if ($type = $app->getRegisteredEntityTypeById($class_name, $value)) {
                    $result = $type->name;
                }
                break;

            case 'publicLocation':
                if ($value) {
                    $result = 'Pública';
                } else if ($value === false) {
                    $result = 'Privada';
                } else {
                    $result = 'Não informada';
                }
                break;
        }

        return $result;
    }

    public function sortDataByNum(&$data)
    {
        usort($data, function ($a, $b) {
            return $b['num'] <=> $a['num'];
        });
    }

    public function getDayOfFirstEntity($entity)
    {
        $app = App::i();

        $cache_id = __METHOD__ . '::' . $entity;

        if ($app->cache->contains($cache_id)) {
            return $app->cache->fetch($cache_id);
        }

        $conn = $app->em->getConnection();

        $result = $conn->fetchColumn("SELECT MIN(create_timestamp::DATE) FROM {$entity} WHERE id > 1 AND status > 0");

        $app->cache->save($cache_id, $result);

        return $result;
    }

    public function getDays($entity)
    {
        $first_day = $this->data['from'] ?? $this->getDayOfFirstEntity($entity);
        $last_day = $this->data['to'] ?? date('Y-m-d');
        $period = new \DatePeriod(
            new \DateTime($first_day),
            new \DateInterval('P1D'),
            new \DateTime($last_day)
        );

        $result = [];
        foreach ($period as $date) {
            $result[] = $date->format('Y-m-d');
        }

        return $result;
    }

    public function extractDailyData($entity, $daily_data, $field = null, $group = null)
    {
        $days = $this->getDays($entity);

        $result = [];

        foreach ($days as $day) {
            $result[$day] = 0;
        }

        foreach ($daily_data as $row) {
            if ($field) {
                if ($row->field == $field) {
                    if ($group) {
                        if ($row->data_group == $group) {
                            $result[$row->day] = $row->num;
                        }
                    } else {
                        $result[$row->day] = $row->num;
                    }
                }
            } else {
                $result[$row->day] = $row->num;
            }
        }

        return array_values($result);
    }

    public function extractData($data, $field = false)
    {
        $result = [];
        foreach ($data as $row) {
            if (!$field || ($row->field == $field)) {
                $result[] = $row->num;
            }
        }

        return $result;
    }

    public function extractDistinctGroups($data, $field = null)
    {
        $result = [];
        foreach ($data as $row) {
            if (!in_array($row->data_group, $result)) {
                if ($field) {
                    if ($field == $row->field) {
                        $result[] = $row->data_group;
                    }
                } else {
                    $result[] = $row->data_group;
                }
            }
        }

        return $result;
    }

    private function color()
    {
        mt_srand((double) microtime() * 1000000);
        $c = '';
        while (strlen($c) < 6) {
            $c .= sprintf("%02X", mt_rand(0, 255));
        }
        return "#" . $c;
    }
}
