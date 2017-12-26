<?php
namespace Breadlesscode\ErrorPages\ViewHelpers;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper;
use Neos\Neos\Routing\FrontendNodeRoutePartHandler;
use Neos\Neos\Domain\Service\ContentDimensionPresetSourceInterface;
use Neos\Neos\View\FusionView;

if (!function_exists('cutLastPartOfPath')) {

    function cutLastPartOfPath($path, $delimiter = '/')
    {
        $pos = strrpos($path, $delimiter);

        if ($pos === false || $pos === 1) {
            return null;
        }

        return substr($path, 0, $pos);
    }
}

if (!function_exists('path2array')) {

    function path2array($path, $delimiter = '/')
    {
        $array = [$path];

        while ($path = cutLastPartOfPath($path))
        {
            $array[] = $path;
        }

        if (\end($array) !== "") {
            $array[] = '';
        }
        return $array;
    }
}

if (!function_exists('comparePaths')) {

    function comparePaths($pathOne, $pathTwo, $delimiter = '/')
    {
        $pathOne = path2array($pathOne);
        $pathTwo = path2array($pathTwo);

        for ($distance = 0; $distance < count($pathOne); $distance++) {
            for ($p2index = 0; $p2index < count($pathTwo); $p2index++) {
                if ($pathOne[$distance] === $pathTwo[$p2index]) {
                    return $distance + $p2index;
                }
            }
        }

        return -1;
    }
}

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
     * @Flow\Inject
     * @var ContentDimensionPresetSourceInterface
     */
    protected $contentDimensionPresetSource;
    /**
     * @param  Neos\Flow\Mvc\Controller\Exception\InvalidControllerException $exception
     * @return string
     */
    public function render($exception)
    {
        $statusCode = $exception->getStatusCode();
        $dimension = $this->getCurrentDimension();
        $errorPage = $this->findErrorPage($statusCode, $dimension);
        if ($errorPage === null) {
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
        $requestPath = cutLastPartOfPath(
            $this->controllerContext
                ->getRequest()
                ->getHttpRequest()
                ->getUri()
                ->getPath()
        );
        // find the correct error page
        $errorPages = $errorPages
            // filter invalid status codes
            ->filter(function ($page) use ($statusCode) {
                $supportedStatusCodes = $page->getProperty('statusCodes');
                return $supportedStatusCodes !== null && \in_array($statusCode, $supportedStatusCodes);
            })
            // filter invalid dimensions
            ->filter(function ($page) use ($dimension) {
                return \in_array($dimension, $page->getDimensions()['language']);
            })
            // filter all pages which not in the correct path
            ->sortBy(function ($page) use ($requestPath, $dimension) {
                return comparePaths(
                    $this->getPathWithoutDimensionPrefix($requestPath),
                    cutLastPartOfPath($this->getPathWithoutDimensionPrefix($this->getUriOfNode($page)))
                );
            });
        return $errorPages->first();
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

        if ($presets->pluck('uriSegment')->contains($matches['firstUriPart'])) {
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
        $requestPath = $this->controllerContext->getRequest()->getHttpRequest()->getUri()->getPath();
        preg_match(FrontendNodeRoutePartHandler::DIMENSION_REQUEST_PATH_MATCHER, ltrim($requestPath, '/'), $matches);

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
