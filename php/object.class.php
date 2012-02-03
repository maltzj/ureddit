<?php

require_once("base.class.php");

class ObjectAttributeNotFoundException extends Exception {}
class ObjectNotFoundException extends Exception {}

class object extends base
{
  public $id = NULL;
  public $ring = NULL;
  public $type = NULL;
  public $value = NULL;
  public $created = NULL;
  public $modified = NULL;
  public $children = NULL;
  public $parents = NULL;
  public $associations = NULL;
  public $attributes = NULL;
  public $dbpdo = NULL;
  public $unsaved = false;
  public $new = true;

  function __autoload($class)
  {
    require_once("$class.class.php");
  }

  function __construct($dbpdo, $id = NULL)
  {
    parent::__construct($dbpdo->config);
    $this->dbpdo = $dbpdo;
    if($id !== NULL)
      $this->lookup($id);
  }

  function use_connection($mh)
  {
    $this->mh = $mh;
  }

  function lookup($id)
  {
    if($this->config->memcache())
      {
	$data = $this->memcache_get('v3_objects_' . $id);
	if(!$data)
	  {
	    $data = $this->dbpdo->query("SELECT * FROM `objects` WHERE `id` = ?", array($id));
	    $this->memcache_set('v3_objects_' . $id, $data);
	  }
      }
    else
      {
	$data = $this->dbpdo->query("SELECT * FROM `objects` WHERE `id` = ?", array($id));
      }

    if(count($data) == 0)
      throw new ObjectNotFoundException;

    $data = $data[0];
    $this->new = false;

    $this->id = $id;
    $this->type = $data['type'];
    $this->value = $data['value'];
    $this->ring = $data['ring'];
    $this->created = $data['creation'];
    $this->modified = $data['modification'];
    $this->unsaved = false;
  }

  function update_type($type)
  {
    $this->type = $type;
    $this->unsaved = true;
  }

  function update_value($value)
  {
    $this->value = $value;
    $this->unsaved = true;
  }

  function update_ring($ring)
  {
    $this->ring = $ring;
    $this->unsaved = true;
  }

  function get_attributes($type = '%')
  {
    $this->attributes = array();
    $attributes = $this->dbpdo->query("SELECT * FROM `object_attributes` WHERE `object_id` = ? AND `type` LIKE ?", array($this->id, $type));
    foreach($attributes as $attribute)
      {
	$this->attributes[$attribute['type']] = array(
						      'id' => $attribute['id'],
						      'value' => $attribute['value'],
						      'ring' => $attribute['ring'],
						      'modified' => false
						      );
      }
  }

  function define_attribute($type, $value, $ring = NULL)
  {
    if(strlen($value) == 0)
      return;

    if($this->attributes === NULL)
      $this->get_attributes($type);

    if(!isset($this->attributes[$type]))
      {
	if($ring === NULL)
	  $this->error('Error: defined a new attribute without giving it a ring.');
	$this->attributes[$type] = array(
					 'id' => NULL,
					 'value' => $value,
					 'new' => true,
					 'ring' => $ring
					 );
      }
    else
      {
	$this->attributes[$type]['value'] = $value;
	$this->attributes[$type]['modified'] = true;
	if($ring !== NULL)
	  $this->attributes[$type]['ring'] = $ring;
      }

    $this->unsaved = true;
  }

  function get_attribute_value($type)
  {
    if($this->config->memcache())
      {
	$value = $this->memcache_get('v3_object_' . $this->id . '_attribute_' . $type);
	if($value !== false)
	  return $value;
      }
    $this->get_attributes($type);
    if(isset($this->attributes[$type]))
      return $this->attributes[$type]['value'];
    else
      throw new ObjectAttributeNotFoundException;
  }

  function remove_attribute($type)
  {
    $this->dbpdo->query("DELETE FROM `object_attributes` WHERE `object_id` = ? AND `type` = ?",
			array(
			      $this->id,
			      $type
			      ));
    if(isset($this->attributes[$type]))
      unset($this->attributes[$type]);
    if($this->config->memcache())
      $this->memecache_delete('v3_object_' . $this->id . '_attribute_' . $type);
  }

  function get_parents($parent_type = '%', $association_type='%', $offset = NULL, $limit = NULL)
  {
    $this->parents = array();
    
    $q = "SELECT p.id AS parent_id, p.type AS parent_type, a.id AS association_id, a.type AS association_type FROM objects AS p INNER JOIN (associations AS a INNER JOIN objects AS c ON a.child_id=c.id AND c.id = ? AND a.type LIKE ?) ON p.id=a.parent_id AND p.type LIKE ?";
    if($offset !== NULL)
      if($limit !== NULL)
	$q .= "LIMIT $offset, $limit";
      else
	$q .= "LIMIT $offset";

    $data = $this->dbpdo->query($q, array($this->id, $association_type, $parent_type));
    foreach($data as $assoc)
      {
	if(!isset($this->parents[$assoc['parent_type']]) || !is_array($this->parents[$assoc['parent_type']]))
	  $this->parents[$assoc['parent_type']] = array();
	$this->parents[$assoc['parent_type']][] = $assoc['parent_id'];
	if(!isset($this->associations[$assoc['association_type']]) || !is_array($this->associations[$assoc['association_type']]))
	  $this->associations[$assoc['association_type']] = array();
	$this->associations[$assoc['association_type']][] = $assoc['association_id'];
      }
  }

  function get_children($child_type = '%', $association_type='%', $offset = NULL, $limit = NULL)
  {
    $this->children = array();

    $q = "SELECT c.id AS child_id, c.type AS child_type, a.id AS association_id, a.type AS association_type FROM objects AS c INNER JOIN (associations AS a INNER JOIN objects AS p ON a.parent_id=p.id AND p.id = ? AND a.type LIKE ?) ON c.id=a.child_id AND c.type LIKE ?";
    if($offset !== NULL)
      if($limit !== NULL)
	$q .= "LIMIT $offset, $limit";
      else
	$q .= "LIMIT $offset";
    $data = $this->dbpdo->query($q, array($this->id, $association_type, $child_type));
    foreach($data as $assoc)
      {
	if(!isset($this->children[$assoc['child_type']]) || !is_array($this->children[$assoc['child_type']]))
	  $this->children[$assoc['child_type']] = array();
	$this->children[$assoc['child_type']][] = $assoc['child_id'];
	if(!isset($this->associations[$assoc['association_type']]) || !is_array($this->associations[$assoc['association_type']]))
	  $this->associations[$assoc['association_type']] = array();
	$this->associations[$assoc['association_type']][] = $assoc['association_id'];
      }
  }

  function add_child($id, $type, $ring)
  {
    return $this->create_association($this->id, $id, $type, $ring);
  }

  function add_parent($id, $type, $ring)
  {
    return $this->create_association($id, $this->id, $type, $ring);
  }

  function create_association($parent_id, $child_id, $type, $ring)
  {
    $date = $this->timestamp();
    return $this->dbpdo->query("INSERT INTO `associations` (`parent_id`,`type`,`child_id`,`ring`,`creation`,`modification`) VALUES(?, ?, ?, ?, ?, ?)", 
			array(
			      $parent_id,
			      $type,
			      $child_id,
			      $ring,
			      $date,
			      $date
			      ));
  }

  function remove_association($parent_id, $child_id, $type = '%')
  {
    $this->dbpdo->query("DELETE FROM `associations` WHERE `parent_id` = ? AND `child_id` = ? AND `type` LIKE ?", 
			array(
			      $parent_id,
			      $child_id,
			      $type
			      ));
  }
      
  function remove_parent($id, $type = '%')
  {
    $this->remove_association($id, $this->id, $type);
  }

  function remove_child($id, $type = '%')
  {
    $this->remove_association($this->id, $id, $type);
  }

  function define($type, $value, $ring)
  {
    if($this->id !== NULL)
      $this->error("Called define() on an object that already exists.");
    $this->type = $type;
    $this->value = $value;
    $this->ring = $ring;
    $this->unsaved = true;
    $this->save();
  }

  function save()
  {
    $date = $this->timestamp();

    if($this->id === NULL)
      {
	if($this->type === NULL || $this->value === NULL || $this->ring === NULL)
	  $this->error("save() called without all object properties being set.");

	$this->id = $this->dbpdo->query("INSERT INTO `objects` (`type`,`value`,`ring`,`creation`,`modification`) VALUES (?, ?, ?, ?, ?)", 
					array(
					      $this->type,
					      $this->value,
					      $this->ring,
					      $date,
					      $date
					      ));
      }
    elseif($this->unsaved = true)
      {
	$this->dbpdo->query("UPDATE `objects` SET `type` = ?, `value` = ?, `ring` = ?, `modification` = ? WHERE `id` = ?", 
					array(
					      $this->type,
					      $this->value,
					      $this->ring,
					      $date,
					      $this->id
					      ));
	if($this->config->memcache())
	  $this->memcache_delete('v3_objects_' . $this->id);
      }

    if($this->attributes !== NULL)
      {
	foreach($this->attributes as $attribute => $info)
	  {
	    if(isset($info['new']) && $info['new'] == true)
	      {
		$this->dbpdo->query("INSERT INTO `object_attributes` (`object_id`,`type`,`value`,`ring`,`creation`,`modification`) VALUES (?, ?, ?, ?, ?, ?)",
				    array(
					  $this->id,
					  $attribute,
					  $info['value'],
					  $info['ring'],
					  $date,
					  $date
					  ));
	      }
	    if(isset($info['modified']) && $info['modified'] == true)
	      {
		$this->dbpdo->query("UPDATE `object_attributes` SET `value` = ?, `ring` = ?, `modification` = ? WHERE `id` = ?", 
				    array(
					  $info['value'],
					  $info['ring'],
					  $date,
					  $info['id']
					  ));
		if($this->memcache())
		  $this->memcache_delete('v3_object_' . $this->id . '_attribute_' . $attribute);
	      }
	  }
      }

    $this->unsaved = false;
  }

  function __destruct()
  {
    if($this->unsaved)
      $this->save();
  }

}

?>