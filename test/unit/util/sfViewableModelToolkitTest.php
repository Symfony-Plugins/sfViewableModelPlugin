<?php

include dirname(__FILE__).'/../../bootstrap/unit.php';

$t = new lime_test(8, new lime_output_color());

class BaseObject
{
}

class PropelModelClass extends BaseObject
{
  public function getPrimaryKey()
  {
    return '==PK==';
  }
}

class Doctrine_Record
{
}

class DoctrineModelClass extends Doctrine_Record
{
  public function identifier()
  {
    return '==PK==';
  }
}

function set_orm($orm)
{
  sfConfig::set('sf_orm', $orm);
}

$propelObject   = new PropelModelClass();
$doctrineObject = new DoctrineModelClass();

$t->diag('::isModelObject()');
set_orm('propel');
$t->ok(sfViewableModelToolkit::isModelObject($propelObject), '::isModelObject() acknowledges a Propel object');
$t->ok(!sfViewableModelToolkit::isModelObject($doctrineObject), '::isModelObject() rejects a Doctrine object');
set_orm('doctrine');
$t->ok(sfViewableModelToolkit::isModelObject($doctrineObject), '::isModelObject() acknowledges a Doctrine object');
$t->ok(!sfViewableModelToolkit::isModelObject($propelObject), '::isModelObject() rejects a Propel object');

$t->diag('::getPrimaryKey()');
set_orm('propel');
$t->is(sfViewableModelToolkit::getPrimaryKey($propelObject), '==PK==', '::getPrimaryKey() returns a Propel PK');
set_orm('doctrine');
$t->is(sfViewableModelToolkit::getPrimaryKey($doctrineObject), '==PK==', '::getPrimaryKey() returns a Doctrine PK');
try
{
  sfViewableModelToolkit::getPrimaryKey($propelObject);
  $t->fail('::getPrimaryKey() throws an exception when passed a non-model object');
}
catch (Exception $e)
{
  $t->pass('::getPrimaryKey() throws an exception when passed a non-model object');
}

$t->diag('::filterModelObjects()');
set_orm('propel');
$input = array($propelObject, array('foo', $anotherPropelObject = clone $propelObject));
$expected = array($propelObject, $anotherPropelObject);
$t->is_deeply(sfViewableModelToolkit::filterModelObjects($input), $expected, '::filterModelObjects() returns a flat array of model objects');
