<?php
namespace Rtgroup\PayrollAgences;

use Rtgroup\Dbconnect\Dbconfig;
use Rtgroup\Dbconnect\Dbconnect;
use Rtgroup\HttpRouter\DataLoader;
use Rtgroup\HttpRouter\HttpRequest;
use Rtgroup\PayrollAdresses\Adresse;

class Agences
{
    /** herite du DataLoader pour etre en mesure de charger les données de reponse à partir de ce composant */
    use DataLoader;

    private $libelle;
    private $userId=null;

    private $adressId=null;

    private HttpRequest $httpRequest;

    private Dbconnect $dbconnect;

    public function __construct()
    {
        $this->httpRequest=HttpRequest::getCachedObject();

        /**
         * Acceder à la config global.
         */
        global $dbconnect;

        $this->dbconnect=$dbconnect;
        $this->dbconnect->setTable("agences");

    }

    /**
     * Ajouter une agence dans la db.
     * @return void
     * @throws \Exception
     */
    public function add()
    {
        if(!$this->httpRequest->isPost())
        {
            throw new \Exception("forbiden request.",404);
        }

        /**
         * Verifier les données obligatoires.
         */
        HttpRequest::checkRequiredData("libelle");
        HttpRequest::checkRequiredData("province");
        HttpRequest::checkRequiredData("commune");
        HttpRequest::checkRequiredData("quartier");
        HttpRequest::checkRequiredData("avenue");
        HttpRequest::checkRequiredData("numero");
        HttpRequest::checkRequiredData("user_id");

        $this->setUser($_POST['user_id']);

        $this->libelle=$_POST['libelle'];

        $this->setAdresse(province: $_POST['province'],commune: $_POST['commune'],quartier: $_POST['quartier'],avenue: $_POST['avenue'],numero: $_POST['numero']);

        $id=$this->save();

        if($id>0)
        {
            $result=[
                "agence_id" => $id,
                "status"     => "success"
            ];
            return $result;
        }
        else
        {
            $result=[
                "agence_id" => 0,
                "status"     => "failed"
            ];
            return $result;
        }
       

       // $this->loadData("reponse",array("status"=>"success","agence_id"=>$id));

    }

    private function setUser($userId)
    {
        //TODO: Check userId.
        $this->userId=$userId;
    }

    /**
     * Adresse de l'agence.
     * @param $province
     * @param $commune
     * @param $avenue
     * @param $numero
     * @return void
     */
    private function setAdresse($province,$commune,$quartier,$avenue,$numero)
    {
        $adresse=new Adresse(province:$province,commune: $commune,quartier: $quartier,avenue: $avenue,numero: $numero);
        $this->adressId=$adresse->save();
    }

    /**
     * Save agence.
     * @return array|bool|int|string
     */
    private function save()
    {
        $this->dbconnect->setTable("agences");
        $data['adresse_id']=(int)$this->adressId;
        $data['libelle']=$this->libelle;
        $data['user_id']=(int)$this->userId;
        $data['date_enregistrement']=time();

        //print_r($data); exit();

        return $this->dbconnect->insert($data);
    }

    /**
     * Recuperer les agents d'une agence.
     * @param $agenceId
     */
    public function getAllAgents($agenceId)
    {

        /**
         * Get all affectations.
         */
        $this->dbconnect->setTable("affectations");
        $this->dbconnect->where("agence_id","=",$agenceId,"AND","affectation_status","=","actif");
        $affectations=$this->dbconnect->select();

        $agentsIds=array();
        for($i=count($affectations)-1; $i>=0; $i--)
        {
            /**
             * Ne consider que la dernière affectation de chaque agent.
             */
            if(!in_array($affectations[$i]['agent_id'],$agentsIds))
            {
                $agentsIds[]=$affectations[$i]['agent_id'];
            }
        }

        /**
         * Get agents data.
         */
        $this->dbconnect->setTable("agents");
        $allAgents=array();
        for($i=0; $i<count($agentsIds); $i++)
        {
            $this->dbconnect->where("agent_id","=",$agentsIds[$i]);
            $a=$this->dbconnect->select();
            if($a)
            {
                $allAgents[]=$a[0];
            }

        }

        return $allAgents;
    }


     /**
     * Enregistrer les dispositifs par agence
     * @param $agenceId
     * @return array|bool|int|string
     */
    public function addDispositifs()
    {

        // HttpRequest::checkRequiredData("agent_id"); //TODO: check.
        HttpRequest::checkRequiredData("libelle"); //TODO:check
        HttpRequest::checkRequiredData("serie"); //TODO: check.
        HttpRequest::checkRequiredData("adresse_ip"); //TODO: check.
        HttpRequest::checkRequiredData("user_id");
        HttpRequest::checkRequiredData("agence_id");

        $data['libelle']=$_POST['libelle'];
        $data['serie']=$_POST['serie'];
        $data['adresse_ip']=$_POST['adresse_ip'];
        $data['user_id']=$_POST['user_id'];
        $data['date_enregistrement']=time();

        $this->dbconnect->setTable("dispositifs");
        $dispositif_id=$this->dbconnect->insert($data);

        $id=$this->addAgenceDispositifs($_POST['agence_id'],$dispositif_id,$_POST['user_id']);

        if($id)
        {
            $result=[
                "agence_dipositif_id" => $id,
                "status"     => "success"
            ];
        return $result;
        }
       
            $result=[
                "affectation_id" => 0,
                "status"     => "failed"
            ];

        return $result;
    }
    /**
     * Enregistrer les agences dispositifs
     * @param 
     * @return array|bool|int|string
     */
    public function addAgenceDispositifs($agence_id,$dispositif_id,$user_id)
    {

        $data['agence_id'] = $agence_id;
        $data['dispositif_id'] = $dispositif_id;
        $data['user_id'] = $user_id;
        $data['date_enregistrement'] = time();

        $this->dbconnect->setTable("agence_dispositifs");
        $id = $this->dbconnect->insert($data);

        if ($id) {
            return $id;
        }
        return null;
    }

    /**
     * @param $returnData
     * @return array|bool|int|string|null
     * @throws \Exception
     */
    public function getAll($returnData=false)
    {
        $this->dbconnect->setTable("agences");
        $this->dbconnect->where(col1: "agence_status",logicOperator1: "=",val1: "actif");
        $this->dbconnect->select();

        $agences=$this->dbconnect->selectJoin(table: "agences",cols: array("agence_id","libelle","date_enregistrement"))
            ->selectJoin(table: "adresses",cols: array("adresse_id","province","commune","quartier","numero"))
            ->join(table_1: "agences",table_2: "adresses",onCol: "adresse_id")
            ->executeJoin();

        /***
         * Format date création agence.
         */
        for($i=0; $i<count($agences); $i++)
        {
            $agences[$i]['date_enregistrement']=date("d/m/Y",$agences[$i]['date_enregistrement']);
        }

        if($returnData)
        {
            /**
             * Retourner les données
             */
            return $agences;
        }
        else
        {

            /**
             * Charger les données au frontend.
             */
            $this->loadData("agences",$agences);
            return null;
        }
    }
}
