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

        $config=new Dbconfig(dbHostname: "localhost",dbName: "milleniumpayroll",dbUsername: "root",dbUserPassword: "");
        $this->dbconnect=new Dbconnect(dbconfig: $config);

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

        $this->loadData("reponse",array("status"=>"success","agence_id"=>$id));

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
     * @return array|bool|int|string
     */
    public function getAllAgents($agenceId)
    {
        $agents=$this->dbconnect->selectJoin(table: "affectations",cols: array("affectation_id","service_id","fonction_id","date_affectation"))
            ->selectJoin(table: "services",cols: array("nom=>service","libelle=>libelle_service"))
            ->selectJoin(table: "fonctions",cols: array("libelle=>fonction"))
            ->joinWhere(table: "affectations",col1: "agence_id",logicOperator1: "=",val1: $agenceId,col2: "affectation_status",logicOperator2: "=",val2: "actif")
            ->executeJoin();

        return $agents;
    }
}