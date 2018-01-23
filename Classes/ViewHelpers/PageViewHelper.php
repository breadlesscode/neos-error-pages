<?php
namespace Breadlesscode\ErrorPages\ViewHelpers;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper;
use Neos\Neos\Routing\FrontendNodeRoutePartHandler;
use Neos\Neos\View\FusionView;
use Breadlesscode\ErrorPages\Utility\PathUtility;

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
     * @return string
     */
    public function render($exception)
    {
        $requestPath = PathUtility::cutLastPart(
            $this->controllerContext
                ->getRequest()
                ->getHttpRequest()
                ->getUri()
                ->getPath()
        );
        $errorPage = $this->findErrorPage($requestPath, $exception->getStatusCode());

        if ($errorPage === null) {
            throw new \Exception("Please setup a error page of type ".self::ERROR_PAGE_TYPE." in your page root!", 1);
        }
        // render error page
        $view = new FusionView();
        $view->setControllerContext($this->controllerContext);
        $view->setFusionPath('errorPages');
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
    protected function findErrorPage($requestPath, $statusCode)
    {
        $dimension = $this->getDimensionOfPath($requestPath);
        $errorPages = collect($this->getErrorPages($dimension));
        $statusCode = (string) $statusCode;
        // find the correct error page
        return $errorPages
            // filter invalid status codes
            ->filter(function ($page) use ($statusCode) {
                $supportedStatusCodes = $page->getProperty('statusCodes');
                return $supportedStatusCodes !== null && \in_array($statusCode, $supportedStatusCodes);
            })
            // filter invalid dimensions
            ->filter(function ($page) use ($dimension) {
                return $dimension !== null ? \in_array($dimension, $page->getDimensions()['language']) : true;
            })
            // remove pages which are not in the path
            ->reject(function ($page) use ($requestPath, $dimension) {
                return PathUtility::compare(
                    $this->getPathWithoutDimensionPrefix($requestPath),
                    PathUtility::cutLastPart($this->getPathWithoutDimensionPrefix($this->getUriOfNode($page)))
                ) === null;
            })
            // sort by distance
            ->sortBy(function ($page) use ($requestPath, $dimension) {
                return PathUtility::compare(
                    $this->getPathWithoutDimensionPrefix($requestPath),
                    PathUtility::cutLastPart($this->getPathWithoutDimensionPrefix($this->getUriOfNode($page)))
                );
            })
            ->first();
    }
    /**
     * return path without the dimension prefix
     *
     * @param  string $path
     * @param  string $dimension
     * @return string
     */
    protected function getPathWithoutDimensionPrefix($path)
    {
        $presets = collect($this->contentDimensionsConfig['presets']);
        $matches = [];
        preg_match(FrontendNodeRoutePartHandler::DIMENSION_REQUEST_PATH_MATCHER, ltrim($path, '/'), $matches);

        if (isset($matches['firstUriPart']) && $presets->pluck('uriSegment')->contains($matches['firstUriPart'])) {
            return substr(ltrim($path, '/'), strlen($matches['firstUriPart']));
        }

        return $path;
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
        $context = [
            'workspaceName' => 'live',
            'currentDateTime' => new \Neos\Flow\Utility\Now(),
            'dimensions' => [],
            'targetDimensions' =>  [],
            'invisibleContentShown' => true,
            'removedContentShown' => false,
            'inaccessibleContentShown' => false
        ];

        if ($dimension !== null) {
            $context['dimensions']['language'] = [$dimension, $this->contentDimensionsConfig['defaultPreset']];
            $context['targetDimensions']['language'][] = $dimension;
        }

        return $this->contextFactory->create($context);
    }
    /**
     * get the current dimension preset key
     *
     * @return string   dimension preset key
     */
    protected function getDimensionOfPath($path)
    {
        $matches = [];
        preg_match(FrontendNodeRoutePartHandler::DIMENSION_REQUEST_PATH_MATCHER, ltrim($path, '/'), $matches);

        $presets = collect($this->contentDimensionsConfig['presets'])
            ->filter(function ($value, $key) use ($matches) {
                $uriSegment = data_get($value, 'uriSegment');
                return (
                    $uriSegment !== null && (
                        $uriSegment === data_get($matches, 'firstUriPart') ||
                        $uriSegment === data_get($matches, 'dimensionPresetUriSegments')
                    )
                );
            });

        if ($presets->count() > 0) {
            return $presets->keys()->first();
        }

        if ($this->supportEmptySegmentForDimensions) {
            return $this->contentDimensionsConfig['defaultPreset'];
        }

        return null;
    }
    /**
     * get the uri of a node
     *
     * @param  Node   $node
     * @return string
     */
    protected function getUriOfNode(Node $node)
    {
        static $uriBuilder = null;

        if ($uriBuilder === null) {
            $uriBuilder = $this->controllerContext->getUriBuilder();
            $uriBuilder->setRequest($this->controllerContext->getRequest()->getMainRequest());
            $uriBuilder->reset();
        }

        return $uriBuilder->uriFor('show', ['node' => $node], 'Frontend\\Node', 'Neos.Neos');
    }
}
