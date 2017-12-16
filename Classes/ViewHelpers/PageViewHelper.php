<?php
namespace Breadlesscode\ErrorPages\ViewHelpers;

use Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\View\FusionView;

function dd($v) {
  \Neos\Flow\var_dump($v);
}

class PageViewHelper extends AbstractViewHelper
{
    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    public function render($exception)
    {
        $statusCode = $exception->getStatusCode();
        $request = $this->controllerContext->getRequest();
        $context = $this->getContext();
        $flowQuery = new FlowQuery([ $context->getNode('/') ]);
        $errorPages = $flowQuery->find('[instanceof Breadlesscode.ErrorPages:Page]')->get();
        $correctErrorPage = null;
        $view = new FusionView();


        foreach ($errorPages as $errorPage) {
            $supportedStatusCodes = $errorPage->getProperty('statusCodes');
            if(!$supportedStatusCodes !== null && \in_array($statusCode, $supportedStatusCodes))
                $correctErrorPage = $errorPage;
        }
        dd($correctErrorPage->getPath());
        $view->setControllerContext($this->controllerContext);
        $view->assign('value', $correctErrorPage);
        return $view->render();
    }

    /**
     * creating context for node search
     *
     * @return Neos\ContentRepository\Domain\Service\Context
     */
    protected function getContext()
    {
        return $this->contextFactory->create([
            'workspaceName' => 'live',
            'currentDateTime' => new \Neos\Flow\Utility\Now(),
            'dimensions' => [],
            'invisibleContentShown' => true,
            'removedContentShown' => false,
            'inaccessibleContentShown' => false
        ]);
    }

}
