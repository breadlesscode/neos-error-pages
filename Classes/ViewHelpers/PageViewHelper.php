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

if(!function_exists('cutLastPartOfPath')) {

    function cutLastPartOfPath($path, $delimiter = '/')
    {
        $pos = strrpos($path, $delimiter);

        if($pos === false || $pos === 1) {
            return null;
        }

        return substr($path, 0, $pos);
    }
}

if(!function_exists('path2array')) {

    function path2array($path, $delimiter = '/')
    {
        $array = [$path];

        while($path = cutLastPartOfPath($path))
        {
            $array[] = $path;
        }

        return $array;
    }
}

if(!function_exists('comparePaths')) {

    function comparePaths($pathOne, $pathTwo, $delimiter = '/')
    {

        var_dump("Compare: ", $pathOne, $pathTwo);
        $pathOne = path2array($pathOne);
        $pathTwo = path2array($pathTwo);


        for($distance = 0;$distance < count($pathOne);$distance++) {
            for($p2index = 0;$p2index < count($pathTwo);$p2index++) {
                if($pathOne[$distance] === $pathTwo[$p2index]) {
                    return $distance;
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
        exit();
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
        $requestPath = cutLastPartOfPath( // cut firts and last part
            $this->controllerContext
                ->getRequest()
                ->getHttpRequest()
                ->getUri()
                ->getPath()
        );
        // find the correct error page
        $errorPages = $errorPages
            // filter invalid status codes
            ->filter(function($page) use($statusCode) {
                $supportedStatusCodes = $page->getProperty('statusCodes');
                return $supportedStatusCodes !== null && \in_array($statusCode, $supportedStatusCodes);
            })
            // filter invalid dimensions
           ->filter(function($page) use($dimension) {
                return \in_array($dimension, $page->getDimensions()['language']);
            })
            // filter all pages which not in the correct path
            ->sortBy(function($page) use($requestPath, $dimension) {
                return comparePaths($requestPath, $this->getPathWithoutDimensionPrefix($this->getUriOfNode($page),$dimension));
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
    protected function getPathWithoutDimensionPrefix($path, $dimension)
    {
        $uriPrefix = $this->contentDimensionsConfig['presets'][$dimension]['uriSegment'];

        if(substr(ltrim($path, '/'), 0, strlen($uriPrefix)) === $uriPrefix) {
            return substr(ltrim($path, '/'), strlen($uriPrefix));
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
    /**
     * get the uri of a node
     *
     * @param  Node   $node
     * @return string
     */
    protected function getUriOfNode(Node $node)
    {
        static $uriBuilder = null;

        if($uriBuilder === null) {
            $uriBuilder = $this->controllerContext->getUriBuilder();
            $uriBuilder->setRequest($this->controllerContext->getRequest()->getMainRequest());
            $uriBuilder->reset();
            \Neos\Flow\var_dump($this->getUriSegmentForDimensions($node->getContext()->getDimensions(), false));
        }

        return $uriBuilder->uriFor('show', ['node' => $node], 'Frontend\\Node', 'Neos.Neos');
    }
        /**
     * Find a URI segment in the content dimension presets for the given "language" dimension values
     *
     * This will do a reverse lookup from actual dimension values to a preset and fall back to the default preset if none
     * can be found.
     *
     * @param array $dimensionsValues An array of dimensions and their values, indexed by dimension name
     * @param boolean $currentNodeIsSiteNode If the current node is actually the site node
     * @return string
     * @throws \Exception
     */
    protected function getUriSegmentForDimensions(array $dimensionsValues, $currentNodeIsSiteNode)
    {
        $uriSegment = '';
        $allDimensionPresetsAreDefault = true;

        foreach ($this->contentDimensionPresetSource->getAllPresets() as $dimensionName => $dimensionPresets) {
            $preset = null;
            if (isset($dimensionsValues[$dimensionName])) {
                $preset = $this->contentDimensionPresetSource->findPresetByDimensionValues($dimensionName, $dimensionsValues[$dimensionName]);
            }
            $defaultPreset = $this->contentDimensionPresetSource->getDefaultPreset($dimensionName);
            if ($preset === null) {
                $preset = $defaultPreset;
            }
            if ($preset !== $defaultPreset) {
                $allDimensionPresetsAreDefault = false;
            }
            if (!isset($preset['uriSegment'])) {
                throw new \Exception(sprintf('No "uriSegment" configured for content dimension preset "%s" for dimension "%s". Please check the content dimension configuration in Settings.yaml', $preset['identifier'], $dimensionName), 1395824520);
            }
            $uriSegment .= $preset['uriSegment'] . '_';
        }

        if ($this->supportEmptySegmentForDimensions && $allDimensionPresetsAreDefault && $currentNodeIsSiteNode) {
            return '/';
        } else {
            return ltrim(trim($uriSegment, '_') . '/', '/');
        }
    }
}
