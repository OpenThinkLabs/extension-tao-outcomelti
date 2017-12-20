<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2014-2017 (original work) Open Assessment Technologies SA;
 *
 *
 */

namespace oat\taoLtiBasicOutcome\models\tasks;

use oat\oatbox\extension\AbstractAction;
use oat\oatbox\log\LoggerAwareTrait;
use oat\taoDelivery\model\execution\ServiceProxy;
use oat\taoOutcomeUi\model\ResultsService;
use oat\taoResultServer\models\classes\ResultAliasServiceInterface;
use taoResultServer_models_classes_OutcomeVariable;

class SendLtiOutcomeTask extends AbstractAction
{

    use LoggerAwareTrait;

    const VARIABLE_IDENTIFIER = 'LtiOutcome';

    public function __invoke($params)
    {
        $report = new \common_report_Report(\common_report_Report::TYPE_ERROR);
        $deliveryResultIdentifier = $params['deliveryResultIdentifier'];
        $consumerKey = $params['consumerKey'];
        $serviceUrl = $params['serviceUrl'];

        try {
            $deliveryExecution = ServiceProxy::singleton()->getDeliveryExecution($deliveryResultIdentifier);
            $resultsService = ResultsService::singleton();
            $implementation = $resultsService->getReadableImplementation($deliveryExecution->getDelivery());
            $resultsService->setImplementation($implementation);

            $variables = $resultsService->getVariableDataFromDeliveryResult($deliveryResultIdentifier, [taoResultServer_models_classes_OutcomeVariable::class]);

            $submitted = 0;
            /** @var taoResultServer_models_classes_OutcomeVariable $variable */
            foreach ($variables as $variable) {
                if (self::VARIABLE_IDENTIFIER == $variable->getIdentifier()) {
                    $this->sendLtiOutcome($variable, $deliveryResultIdentifier, $consumerKey, $serviceUrl);
                    $submitted++;
                }
                break;
            }
            if (0 === $submitted){
                throw new \common_Exception('No LTI Outcome has been submitter for execution' . $deliveryResultIdentifier);
            }
        } catch (\Exception $exception) {
            $report->setMessage($exception->getMessage());
        }

        $report->setType(\common_report_Report::TYPE_SUCCESS);
        return $report;

    }

    /**
     * @param taoResultServer_models_classes_OutcomeVariable $testVariable
     * @param $deliveryResultIdentifier
     * @param $consumerKey
     * @param $serviceUrl
     * @return bool
     * @throws \common_exception_Error
     * @throws \taoLti_models_classes_LtiException
     * @throws \tao_models_classes_oauth_Exception
     */
    private function sendLtiOutcome(taoResultServer_models_classes_OutcomeVariable $testVariable, $deliveryResultIdentifier, $consumerKey, $serviceUrl)
    {
        $grade = (string)$testVariable->getValue();

        /** @var ResultAliasServiceInterface $resultAliasService */
        $resultAliasService = $this->getServiceLocator()->get(ResultAliasServiceInterface::SERVICE_ID);
        $deliveryResultAlias = $resultAliasService->getResultAlias($deliveryResultIdentifier);
        $deliveryResultIdentifier = empty($deliveryResultAlias) ? $deliveryResultIdentifier : current($deliveryResultAlias);

        $message = \taoLtiBasicOutcome_helpers_LtiBasicOutcome::buildXMLMessage($deliveryResultIdentifier, $grade, 'replaceResultRequest');

        $credentialResource = \taoLti_models_classes_LtiService::singleton()->getCredential($consumerKey);
        $credentials = new \tao_models_classes_oauth_Credentials($credentialResource);
        //Building POX raw http message
        $unSignedOutComeRequest = new \common_http_Request($serviceUrl, 'POST', array());
        $unSignedOutComeRequest->setBody($message);
        $signingService = new \tao_models_classes_oauth_Service();
        $signedRequest = $signingService->sign($unSignedOutComeRequest, $credentials, true);
        $this->logDebug("Request sent (Body)\n" . $signedRequest->getBody() . "\n");
        $this->logDebug("Request sent (Headers)\n" . serialize($signedRequest->getHeaders()) . "\n");
        $this->logDebug("Request sent (Headers)\n" . serialize($signedRequest->getParams()) . "\n");
        //Hack for moodle compatibility, the header is ignored for the signature computation
        $signedRequest->setHeader("Content-Type", "application/xml");

        $response = $signedRequest->send();
        $this->logDebug("\nHTTP Code received: " . $response->httpCode . "\n");
        $this->logDebug("\nHTTP From: " . $response->effectiveUrl . "\n");
        $this->logDebug("\nHTTP Content received: " . $response->responseData . "\n");
        if ('200' != $response->httpCode) {
            throw new \common_exception_Error('An HTTP level problem occurred when sending the outcome to the service url');
        }
        return true;
    }
}