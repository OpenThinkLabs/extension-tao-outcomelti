<?php

//require_once("taoLtiBasicOutcome/includes/ims-blti/OAuthBody.php");

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
        common_ext_ExtensionsManager::singleton()->getExtensionById("taoLtiBasicOutcome");
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
       
        if (get_class($testVariable)=="taoResultServer_models_classes_OutcomeVariable") {
            common_Logger::i("*************Starting Outcome submission ********");
            common_Logger::i("Outcome test Variable Submission: ".$testVariable->getIdentifier() );
            common_Logger::i("Result Identifier: ".$deliveryResultIdentifier );
            common_Logger::i("Service URL: ".$this->serviceUrl );
            $variableIdentifier = $testVariable->getIdentifier();
            //if in_array($variableIdentifier, array("numberCorrect", "numberSelected", "ratio"))
            //
            $grade = $testVariable->getValue();
            $message = taoLtiBasicOutcome_helpers_LtiBasicOutcome::buildXMLMessage($deliveryResultIdentifier, $grade, 'replaceResultRequest');

            //common_Logger::i("Preparing POX message for the outcome service :".$message."\n");

            $credentialResource = taoLti_models_classes_LtiService::singleton()->getCredential($this->consumerKey);
            //common_Logger::i("Credential for the consumerKey :". $credentialResource->getUri()."\n");
            $credentials = new tao_models_classes_oauth_Credentials($credentialResource);
            //$this->serviceUrl = "http://tao-dev/log.php";
            //Building POX raw http message
            $unSignedOutComeRequest = new common_http_Request($this->serviceUrl, 'POST', array());
            $unSignedOutComeRequest->setBody($message);
            //seems ignored by moodle for the base string computation 
            //$unSignedOutComeRequest->setHeader("Content-Type", "application/xml");
            $signingService = new tao_models_classes_oauth_Service();
            $signedRequest = $signingService->sign($unSignedOutComeRequest, $credentials, true );
            
           // $signedRequest->setBody($message);
            //common_Logger::i("Signed Request :\n\n". serialize($signedRequest)."\n\n");
            $response = $signedRequest->send();
            common_Logger::i("Response received from the outcome service \n\n".serialize($response)."\n" );
            common_Logger::i("*************Ending Outcome submission ********");
            //var_dump($response);
            
        }
       
    }

    /*
         * retrieve specific parameters from the resultserver to configure the storage
         */
    /*sic*/
    public function configure(core_kernel_classes_Resource $resultserver, $callOptions = array()) {
        /**
         * Retrieve the lti consumer associated with the result server in the KB , those rpoperties are available within taoLtiBasicComponent only
         */
       
        if (isset($callOptions["service_url"])) {
            $this->serviceUrl =  $callOptions["service_url"];
        } else {

            throw new common_Exception("LtiBasicOutcome Storage requires a call parameter service_url");
        }
        if (isset($callOptions["consumer_key"])) {
            $this->consumerKey =  $callOptions["consumer_key"];
        } else {
            throw new common_Exception("LtiBasicOutcome Storage requires a call parameter consumerKey");
        }

        common_Logger::i("ResultServer configured with  ".$callOptions["service_url"]. "and ".$callOptions["consumer_key"]);
        
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
            //for testing purpose
            common_Logger::i("Item Variable Submission: ".$itemVariable->getIdentifier() );
            $this->storeTestVariable($deliveryResultIdentifier, $test, $itemVariable, $callIdItem);

    }

}
?>