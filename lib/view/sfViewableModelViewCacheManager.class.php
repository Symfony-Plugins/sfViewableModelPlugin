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
    $viewableModelCacheModified = false,
    $viewableModelCache         = array(),
    $viewableModelCacheFile     = null,
    $ignoreNextCheck            = false,
    $lastCheckedUri             = null;

  /**
   * @see sfViewCacheManager
   */
  public function initialize($context, sfCache $cache)
  {
    parent::initialize($context, $cache);

    $this->viewableModelCacheFile = sfConfig::get('sf_config_cache_dir').'/viewableModel.php';

    if (file_exists($this->viewableModelCacheFile))
    {
      $this->viewableModelCache = include $this->viewableModelCacheFile;
    }

    register_shutdown_function(array($this, 'saveViewableModelCache'));
  }

  /**
   * @see sfViewCacheManager
   */
  public function isCacheable($internalUri)
  {
    $this->lastCheckedUri = $internalUri;
    return parent::isCacheable($internalUri);
  }

  /**
   * Associates a model object to a cache key.
   * 
   * @param mixed  $model
   * @param string $internalUri
   */
  public function connectModelToTemplate($model, $internalUri)
  {
    if (!parent::isCacheable($internalUri))
    {
      return;
    }

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
   * Listens for the template.filter_parameters event.
   * 
   * @param   sfEvent $event
   * @param   array   $parameters
   * 
   * @return  array
   */
  public function filterTemplateParameters(sfEvent $event, array $parameters)
  {
    $models = sfViewableModelToolkit::filterModelObjects(sfOutputEscaper::unescape($parameters));

    foreach ($models as $model)
    {
      $this->connectModelToTemplate($model, $this->lastCheckedUri);
    }

    return $parameters;
  }

  /**
   * Saves the cache of viewable models.
   */
  public function saveViewableModelCache()
  {
    if ($this->viewableModelCacheModified)
    {
      file_put_contents($this->viewableModelCacheFile, '<?php return '.var_export($this->viewableModelCache, true).';');
    }
  }
}
