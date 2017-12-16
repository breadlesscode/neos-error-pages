<?php
namespace Breadlesscode\ErrorPages\ViewHelpers;

use Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\View\FusionView;

class PageViewHelper extends AbstractViewHelper
{
    const ERROR_PAGE_TYPE = 'Breadlesscode.ErrorPages:Page';
    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    public function render($exception)
    {
        $statusCode = $exception->getStatusCode();
        $request = $this->controllerContext->getRequest();
        $errorPages = $this->getErrorPages();
        $pageToRender = null;
        // find the correct error page
        foreach ($errorPages as $errorPage) {
            $supportedStatusCodes = $errorPage->getProperty('statusCodes');
            if(!$supportedStatusCodes !== null && \in_array($statusCode, $supportedStatusCodes))
                $pageToRender = $errorPage;
        }

        if($pageToRender === null)
            throw new \Exception("Please setup a error page of type ".self::ERROR_PAGE_TYPE."!", 1);

        // render error page
        $view = new FusionView();
        $view->setControllerContext($this->controllerContext);
        $view->assign('value', $pageToRender);

        return $view->render();
    }
    /**
     * collects all error pages from the site
     *
     * @return array    collection of error pages
     */
    protected function getErrorPages()
    {
        $context = $this->getContext();

        return (new FlowQuery([ $context->getNode('/') ]))
            ->find('[instanceof '. self::ERROR_PAGE_TYPE .']')
            ->get();
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
