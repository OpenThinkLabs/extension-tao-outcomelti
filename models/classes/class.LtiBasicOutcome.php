<?php

require_once("taoLtiBasicOutcome/includes/ims-blti/OAuthBody.php");

//The ResultStorage does not provide a good interface for the LTI submission case, may require some more abstract interface

// LtiBasicOutcome relies on a speciifc property added to the result server that is the lti consumer which to send the results to (secret is retrieved)
// LtiBasicOutcome relies on a custom option given by the service submitting results taht is the url

class taoLtiBasicOutcome_models_classes_LtiBasicOutcome
    extends tao_models_classes_GenerisService
    implements taoResultServer_models_classes_ResultStorage {

    private $ltiConsumer;//the kb resource modelling the LTI consumer
    /**
    * @param string deliveryResultIdentifier if no such deliveryResult with this identifier exists a new one gets created
    */

    public function __construct(){
		parent::__construct();
        common_ext_ExtensionsManager::getExtensionById("taoLtiBasicOutcome");
        //$this->consumer
       
    }

    /**
     *
     * @param type $deliveryResultIdentifier lis_result_sourcedid
     * @param type $test ignored
     * @param taoResultServer_models_classes_Variable $testVariable
     * @param type $callIdTest ignored
     */

    public function storeTestVariable($deliveryResultIdentifier, $test, taoResultServer_models_classes_Variable $testVariable, $callIdTest){
        //BAsic Lti Outcome restrict the submitted information to a few outcomevariables
        if (get_class($testVariable)=="taoResultServer_models_classes_OutcomeVariable") {
            $grade = $testVariable->getValue();
            $message = taoLtiBasicOutcome_helpers_LtiBasicOutcome::buildXMLMessage($deliveryResultIdentifier, $grade, 'replaceResultRequest');
        }
        //temp solution, need to move to joel's version for signing
        $response = sendOAuthBodyPOST("POST", $this->serviceUrl, $this->consumerKey, $this->secret, "application/xml", $message);
    }

    /*
         * retrieve specific parameters from the resultserver to configure the storage
         */
    /*sic*/
    public function configure(core_kernel_classes_Resource $resultserver, $callOptions = array()) {
        /**
         * Retrieve the lti consumer associated with the result server in the KB , those rpoperties are available within taoLtiBasicComponent only
         */

        /*
         * Retireve the required connection information 
         */
        $parameters = array(
            "serviceUrl" => "http://localhost/calledbackservice",
            "secret" => "mySecret",
            "consumerKey"=> "MyConsumerKEy"

        );
        if (isset($callOptions["serviceUrl"])) {
            $this->serviceUrl = $parameters["serviceUrl"];
        } else {
            throw new common_Exception("LtiBasicOutcome Storage requires a call parameter serviceUrl");
        }
        
        $this->secret = $parameters["secret"];
        $this->consumerKey = $parameters["consumerKey"];
    }

     /**
     * In the case of An LtiBasic OutcomeSubmission, spawnResult is not supported
     */
    public function spawnResult(){
       //
    }
    public function storeRelatedTestTaker($deliveryResultIdentifier, $testTakerIdentifier) {

    }

    public function storeRelatedDelivery($deliveryResultIdentifier, $deliveryIdentifier) {

    }

    public function storeItemVariable($deliveryResultIdentifier, $test, $item, taoResultServer_models_classes_Variable $itemVariable, $callIdItem){

    }

}
?>