<?php
namespace Breadlesscode\ErrorPages\ViewHelpers;

use Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\View\FusionView;
use Neos\Neos\Routing\FrontendNodeRoutePartHandler;


class PageViewHelper extends AbstractViewHelper
{
    /**
     * error page type name
     */
    const ERROR_PAGE_TYPE = 'Breadlesscode.ErrorPages:Page';
    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;
    /**
     * @Flow\InjectConfiguration(path="contentDimensions.language", package="Neos.ContentRepository")
     * @var array
     */
    protected $contentDimensionsConfig = [];
    /**
     * @Flow\InjectConfiguration(path="routing.supportEmptySegmentForDimensions", package="Neos.Neos")
     * @var boolean
     */
    protected $supportEmptySegmentForDimensions;
    /**
     * @param  Neos\Flow\Mvc\Controller\Exception\InvalidControllerException $exception
     * @return string
     */
    public function render($exception)
    {
        $statusCode = $exception->getStatusCode();
        $dimension = $this->getCurrentDimension();
        $errorPage = $this->findErrorPage($statusCode, $dimension);

        if($errorPage === null) {
            throw new \Exception("Please setup a error page of type ".self::ERROR_PAGE_TYPE."!", 1);
        }
        // render error page
        $view = new FusionView();
        $view->setControllerContext($this->controllerContext);
        $view->setFusionPath('default');
        $view->assign('value', $errorPage);

        return $view->render();
    }
    /**
     * find error page in a specific dimension with status code xy in it
     *
     * @param  mixed $statusCode
     * @param  string $dimension
     * @return Neos\ContentRepository\Domain\Model\Node
     */
    protected function findErrorPage($statusCode, $dimension)
    {
        $errorPages = collect($this->getErrorPages($dimension));
        $statusCode = (string) $statusCode;
        // find the correct error page
        return $errorPages
            // filter invalid status codes
            ->filter(function($page) use($statusCode) {
                $supportedStatusCodes = $page->getProperty('statusCodes');

                return $supportedStatusCodes !== null && \in_array($statusCode, $supportedStatusCodes);
            })
            // filter invalid dimensions
            ->filter(function($page) use($dimension) {
                return \in_array($dimension, $page->getDimensions()['language']);
            })
            ->first();
    }
    /**
     * collects all error pages from the site
     *
     * @return array    collection of error pages
     */
    protected function getErrorPages($dimension)
    {
        $context = $this->getContext($dimension);

        return (new FlowQuery([ $context->getNode('/') ]))
            ->find('[instanceof '. self::ERROR_PAGE_TYPE .']')
            ->get();
    }
    /**
     * creating context for node search
     *
     * @return Neos\ContentRepository\Domain\Service\Context
     */
    protected function getContext($dimension)
    {
        return $this->contextFactory->create([
            'workspaceName' => 'live',
            'currentDateTime' => new \Neos\Flow\Utility\Now(),
            'dimensions' => ['language' => [$dimension, $this->contentDimensionsConfig['defaultPreset']]],
            'targetDimensions' =>  ['language' => $dimension],
            'invisibleContentShown' => true,
            'removedContentShown' => false,
            'inaccessibleContentShown' => false
        ]);
    }
    /**
     * get the current dimension preset key
     *
     * @return string   dimension preset key
     */
    protected function getCurrentDimension()
    {
        $matches = [];
        $requestPath = ltrim($this->controllerContext->getRequest()->getHttpRequest()->getUri()->getPath(), '/');
        preg_match(FrontendNodeRoutePartHandler::DIMENSION_REQUEST_PATH_MATCHER, $requestPath, $matches);

        $presets = collect($this->contentDimensionsConfig['presets'])
            ->filter(function($value, $key) use($matches) {
                $uriSegment = data_get($value, 'uriSegment');
                return (
                    $uriSegment !== null && (
                        $uriSegment === data_get($matches, 'firstUriPart') ||
                        $uriSegment === data_get($matches, 'dimensionPresetUriSegments')
                    )
                );
            });

        if($presets->count() > 0) {
            return $presets->keys()->first();
        }

        if($this->supportEmptySegmentForDimensions) {
            return $this->contentDimensionsConfig['defaultPreset'];
        }

        return null;
    }

}
