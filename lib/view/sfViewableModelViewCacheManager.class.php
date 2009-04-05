<?php

/**
 * View cache manager.
 * 
 * @package     sfViewableModelPlugin
 * @subpackage  view
 * @author      Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @version     SVN: $Id$
 */
class sfViewableModelViewCacheManager extends sfViewCacheManager
{
  protected
    $viewableModelCache         = array(),
    $viewableModelClasses       = array(),
    $viewableModelCacheFile     = null,
    $viewableModelCacheModified = false,
    $lastChecked                = array();

  /**
   * @see sfViewCacheManager
   */
  public function initialize($context, sfCache $cache)
  {
    parent::initialize($context, $cache);

    $this->viewableModelCacheFile = sfConfig::get('sf_config_cache_dir').'/viewableModel.php';

    if (!$this->checkViewableModelCache())
    {
      $this->initializeViewableModelClasses();
    }

    foreach ($this->viewableModelClasses as $model)
    {
      sfViewableModelToolkit::extendModel($model);
    }

    register_shutdown_function(array($this, 'saveViewableModelCache'));
  }

  /**
   * Introspects the internal array of model classes.
   */
  protected function initializeViewableModelClasses()
  {
    $this->viewableModelCacheModified = true;
    $this->viewableModelClasses = sfViewableModelToolkit::getAllModelClasses();
  }

  /**
   * @see sfViewCacheManager
   */
  public function isCacheable($moduleName, $actionName = null)
  {
    $isCacheable = parent::isCacheable($moduleName, $actionName);

    $this->lastChecked = array($moduleName, $actionName, $isCacheable);

    return $isCacheable;
  }

  /**
   * Listens for the template.filter_parameters event.
   * 
   * @param   sfEvent $event
   * @param   array   $parameters
   * 
   * @return  array
   */
  public function filterTemplateParameters(sfEvent $event, array $parameters)
  {
    list($moduleName, $actionName, $isCacheable) = $this->lastChecked;

    if (!$isCacheable)
    {
      return $parameters;
    }

    if ($models = sfViewableModelToolkit::filterModelObjects(sfOutputEscaper::unescape($parameters)))
    {
      // generate internal uri, including cache key if necessary
      $internalUri = is_null($actionName) ? $moduleName : ('partial' == $partial['sf_type'] ? $this->getPartialUri($moduleName, $actionName, $this->checkCacheKey($parameters)) : $this->routing->getCurrentInternalUri());

      foreach ($models as $model)
      {
        $this->connectModelToTemplate($model, $internalUri);
      }
    }

    return $parameters;
  }

  /**
   * Associates a model object to a cache key.
   * 
   * @param mixed  $model
   * @param string $internalUri
   */
  public function connectModelToTemplate($model, $internalUri)
  {
    $key = $this->getModelKey($model);

    if (!isset($this->viewableModelCache[$key]))
    {
      $this->viewableModelCache[$key] = array();
    }

    if (!in_array($internalUri, $this->viewableModelCache[$key]))
    {
      if (sfConfig::get('sf_logging_enabled'))
      {
        $this->context->getEventDispatcher()->notify(new sfEvent($this, 'application.log', array(
          sprintf('Caching connection from model "%s" to URI "%s".', $key, $internalUri),
        )));
      }

      $this->viewableModelCache[$key][] = $internalUri;
      $this->viewableModelCacheModified = true;
    }
    else
    {
      if (sfConfig::get('sf_logging_enabled'))
      {
        $this->context->getEventDispatcher()->notify(new sfEvent($this, 'application.log', array(
          sprintf('Cache of connection from model "%s" to URI "%s" exists.', $key, $internalUri),
        )));
      }
    }
  }

  /**
   * Removes caches of the supplied model object.
   * 
   * @param mixed $model
   * 
   * @return boolean
   * 
   * @see sfViewCacheManager::remove()
   */
  public function removeModel($model, $hostName = '', $vary = '', $contextualPrefix = '**')
  {
    $key = $this->getModelKey($model);
    $ret = false;

    if (sfConfig::get('sf_logging_enabled'))
    {
      $this->context->getEventDispatcher()->notify(new sfEvent($this, 'application.log', array(
        sprintf('Removing cached views for model "%s".', $key),
      )));
    }

    if (isset($this->viewableModelCache[$key]))
    {
      foreach ($this->viewableModelCache[$key] as $internalUri)
      {
        if ($this->remove($internalUri, $hostName, $vary, $contextualPrefix))
        {
          $ret = true;
        }
      }
    }

    return $ret;
  }

  /**
   * Returns the key used to identify the supplied model object.
   * 
   * @return string
   */
  public function getModelKey($model)
  {
    if (!sfViewableModelToolkit::isModelObject($model))
    {
      throw new InvalidArgumentException('The argument is not a model object.');
    }

    if (is_array($pk = sfViewableModelToolkit::getPrimaryKey($model)))
    {
      $pk = join('_', $pk);
    }

    $key = get_class($model).'_'.$pk;

    return $key;
  }

  /**
   * Reads the cache of viewable models.
   * 
   * @return boolean True if a cached file was read
   */
  public function checkViewableModelCache()
  {
    if (file_exists($this->viewableModelCacheFile))
    {
      list($this->viewableModelCache, $this->viewableModelClasses) = include $this->viewableModelCacheFile;
      return true;
    }

    return false;
  }

  /**
   * Saves the cache of viewable models.
   */
  public function saveViewableModelCache()
  {
    if ($this->viewableModelCacheModified)
    {
      file_put_contents($this->viewableModelCacheFile, sprintf("<?php\n\nreturn %s;\n", var_export(array(
        $this->viewableModelCache,
        $this->viewableModelClasses,
      ), true)));
    }
  }
}
