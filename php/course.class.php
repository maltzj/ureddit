<?php

class CourseNotFoundException extends Exception {}

class course extends object
{
  public $roster = NULL;
  public $teachers = NULL;
  public $reports = NULL;
  public $owner = NULL;
  public $categories = NULL;

  function __construct($dbpdo, $id = NULL)
  {
    parent::__construct($dbpdo, $id);
  }

  function get_owner()
  {
    $this->get_parents('user','owner');
    if(isset($this->parents['user']))
      $this->owner = $this->parents['user'][0];
    else
      $this->owner = array();
  }

  function assign_owner($id)
  {
    if($this->owner !== NULL)
      $this->remove_association($this->owner, $this->id, 'owner');
    $this->add_parent($id, 'owner', '0');
    $this->owner = $id;
  }

  function get_roster_with_attribute($attribute)
  {
    if($this->config->memcache())
      {
	$data = $this->memcache_get('v3_roster_' . $this->id . '_with_attribute_' . $attribute);
	if(!$data)
	  $data = $this->dbpdo->query("SELECT o.value, oa.value FROM (associations AS a INNER JOIN objects AS o ON a.parent_id = ? AND o.id = a.child_id AND a.type = ?) LEFT OUTER JOIN object_attributes AS oa ON o.id = oa.object_id AND oa.type = ?",
				      array(
					    $this->id,
					    'enrolled_student',
					    $attribute
					    ));
	$this->memcache_set('v3_roster_' . $this->id . '_with_attribute_' . $attribute, $data);
	return $data;
      }
    else
      {
	return $this->dbpdo->query("SELECT o.value, oa.value FROM (associations AS a INNER JOIN objects AS o ON a.parent_id = ? AND o.id = a.child_id AND a.type = ?) LEFT OUTER JOIN object_attributes AS oa ON o.id = oa.object_id AND oa.type = ?",
				   array(
					 $this->id,
					 'enrolled_student',
					 $attribute
					 ));
      }

  }

  function get_roster()
  {
    $this->get_children('user','enrolled_student');
    if(isset($this->children['user']))
      $this->roster = $this->children['user'];
    else
      $this->roster = array();
  }

  function get_teachers()
  {
    $this->get_parents('user','teacher');
    if(isset($this->parents['user']))
      $this->teachers = $this->parents['user'];
    else
      $this->teachers = array();
  }

  function add_teacher($id)
  {
    if($this->teachers == NULL)
      {
	$this->add_parent($id, 'teacher', 0);
	$this->teachers = array($id);
      }
    elseif(is_array($this->teachers) && !in_array($this->teachers, $id))
      {
	$this->add_parent($id, 'teacher', 0);
	$this->teachers[] = $id;
      }
  }

  function get_reports()
  {
    $this->get_parents('user','report');
    if(isset($this->parents['user']))
      $this->reports = $this->parents['user'];
    else
      $this->reports = array();
  }

  function get_categories()
  {
    $this->get_parents('category', 'categorization');
    if(isset($this->parents['category']))
      $this->categories = $this->parents['category'];
    else
      $this->categories = array();
  }

  function add_to_category($id)
  {
    if($this->categories == NULL)
      $this->get_categories();
    if(!in_array($id, $this->categories))
      {
	$this->add_parent($id, 'categorization', 0);
	$this->categories[] = $id;
      }
  }

  function remove_from_category($id)
  {
    $this->remove_parent($id, 'categorization');
    $this->get_categories();
  }

  function mass_message($subject, $message, $author)
  {
    if($this->roster === NULL)
      $this->get_roster();

    try
      {
	$author = new user($this->dbpdo, $this->session('user_id'));
      }
    catch(ObjectNotFoundException $e)
      {
	return;
      }

    foreach($this->roster as $user_id)
      {
	$association_id = $this->create_association($this->id, $user_id, 'unread_mass_message', 0);
	$date = $this->timestamp();
	$this->dbpdo->query("INSERT INTO `association_attributes` (`association_id`, `type`,`value`,`ring`,`creation`,`modification`) VALUES (?, ?, ?, ?, ?, ?)",
			array(
			      $association_id,
			      'subject',
			      $subject,
			      0,
			      $date,
			      $date
			      ));

	$this->dbpdo->query("INSERT INTO `association_attributes` (`association_id`, `type`,`value`,`ring`,`creation`,`modification`) VALUES (?, ?, ?, ?, ?, ?)",
			array(
			      $association_id,
			      'body',
			      $message,
			      0,
			      $date,
			      $date
			      ));

	$this->dbpdo->query("INSERT INTO `association_attributes` (`association_id`, `type`,`value`,`ring`,`creation`,`modification`) VALUES (?, ?, ?, ?, ?, ?)",
			array(
			      $association_id,
			      'author',
			      $author->id,
			      0,
			      $date,
			      $date
			      ));
      }
  }

  function display($expanded = false, $full = false)
  {
    if($this->session('user_id') !== false)
      $user = new user($this->dbpdo, $this->session('user_id'));
    else
      $user = $this->dbpdo;

    ?>
    <div id="class<?=$this->id ?>">
      <div class="class">
        <?php
        if(!$full)
          {
	    ?>
            <div style="font-size: 0.8em; font-weight:bold; float:left; padding-right: 8px;">
              [<a
                style="cursor: pointer;"
                onclick="$.get('<?=PREFIX ?>/show_class.php',{id: '<?=$this->id ?>', show: '<?=$expanded == 'true' ? 'false' : 'true' ?>'}, function(data){$('#class<?=$this->id ?>').html(data);});"
              ><?=($expanded == true ? "-" : "+") ?></a>]
            </div> 
            <?php
	  }
            signup_button($user,$this->id);
        ?>
      <div class="class-name">
        <?php
        echo htmlspecialchars(stripslashes($this->value));
        try
	  {
	    if($this->get_attribute_value('live') == 'true')
	      {
		?>
		<img src="<?=PREFIX ?>/images/live.png" alt="live class!" class="live"  />
	        <span style="font-style: italic; font-weight: normal; font-size: 0.8em;">
		  live lectures!
                </span>
		<?php
	      }
	    if($this->teachers === NULL)
	      $this->get_teachers();
	    if(in_array($this->session('user_id'), $this->teachers))
	      {
		?>
		<span style="font-weight: normal; font-size: 0.8em;">
		  [ <a href="<?=PREFIX ?>/class/<?=$this->id ?>/edit">edit</a> ]
		  [ <a href="<?=PREFIX ?>/class/<?=$this->id ?>/message">mass message</a> ]
		</span>
		<?php
	      }
	    ?><br /><?php
	  }
	catch (ObjectAttributeNotFoundException $e)
	  {

	  }
        ?>
        </div>
        <?php 

        if($expanded == true)
	  {
            ?>
            <div class="class-desc">
              <?php
	      try
	        {
		  echo process(stripslashes($this->get_attribute_value('description')));
		}
  	      catch (ObjectAttributeNotFoundException $e)
		{
		  echo "<em>no description</em>";
		}
	      ?>
            </div>
	    <div class="class-info">
            <?php
            try
              {
		$teachers = array();
		foreach($this->teachers as $teach)
		  {
		    $user = new user($this->dbpdo, $teach);
		    if(strlen($user->value) == 0)
		      throw new ObjectNotFoundException;
		    $text = "<a href=\"" . PREFIX  . "/user/" . $user->value . "\" class=\"link-class-desc\">" . $user->value . "</a>";
		    try
		      {
			$ru = $user->get_attribute_value('reddit_username');
			$text .= "<a href=\"http://reddit.com/user/$ru\"><img style=\"border: 0; width: 1em; height: 1em; margin: 0 3px;\" src=\"" . PREFIX . "/images/reddit.png\"></a>";
		      }
		    catch (ObjectAttributeNotFoundException $e)
		      {

		      }
		    $teachers[] = $text;
		  }
		    
		echo 'taught by ' . implode($teachers, ", ") . ' ';
              }
	    catch (ObjectNotFoundException $e)
	      {
	      echo 'error: teacher not found ';
	      }
	    catch (ObjectAttributeNotFoundException $e)
	      {

	      }

	    try
	      {
                echo "[<a href=\"" . htmlspecialchars(stripslashes($this->get_attribute_value('url'))) . "\" class=\"link-class-desc\">class URL</a>] ";
	      }
   	    catch (ObjectAttributeNotFoundException $e)
              {
	      }
            if(exec("ls files | grep class" . $this->id))
              echo "[<a href=\"/class/" . $this->id . "/files\" class=\"link-class-desc\">class files</a>] ";

            ?>
            [<a href="<?=PREFIX ?>/class/<?=$this->id ?>" class="link-class-desc">class page</a>]
            <?php
	    if(logged_in())
	      {
		?>
		<span id="report<?=$this->id ?>">[<a class="link-class-desc" style="text-decoration: underline; cursor: pointer;" onclick="$.post('<?=PREFIX ?>/report.php',{class: <?=$this->id ?>},function(response){$('#report<?=$this->id ?>').html(response); return false;});">report class]</a></span>
		<?php
	      }
	    ?>
	    </div>
	    <?php
	  } 
        ?>
        <?php
	if($full)
	  {
	    ?>
	      <br /><br />
              <div class="class-name">
                Prerequisites
	      </div>
	      <div class="class-desc">
	      <?php
	      try
	        {
		  echo process(stripslashes($this->get_attribute_value('prerequisites')));
		}
	      catch (ObjectAttributeNotFoundException $e)
		{
		  echo "<em>none</em>";
		}

	      ?>
	      </div>
	      <br /><br />

	      <div class="class-name">
	        Syllabus
	      </div>
	      <div class="class-desc">
	      <?php
	      try
		{
		  echo process(stripslashes($this->get_attribute_value('syllabus')));
		}
	      catch (ObjectAttributeNotFoundException $e)
		{
		  echo "<em>none</em>";
		}
	      ?>
	      </div>
	      <br /><br />

	      <div class="class-name">
	        Additional information
	      </div>
	      <div class="class-desc">
	      <?php
	      try
		{
		  echo process(stripslashes($this->get_attribute_value('additional_information')));
		}
	      catch (ObjectAttributeNotFoundException $e)
		{
		  echo "<em>none</em>";
		}
	      ?>
	      </div>
	      <br /><br />

	      <div class="class-name">
		Teacher qualifications
	      </div>
	      <div class="class-desc">
	      <?php
	      try
		{
		  echo process(stripslashes($this->get_attribute_value('teacher_qualifications')));
		}
	      catch (ObjectAttributeNotFoundException $e)
		{
		  echo "<em>not given</em>";
		}
	      ?>
	      </div>
	      <br /><br />

	      <div class="class-name">
	        Roster
	      </div>
	      <div class="class-desc">
	      <?php 
	      //$data = $this->get_children_with_attribute('user','enrolled_student','reddit_username');
	      $data = $this->get_roster_with_attribute('reddit_username');
	      /*
	      $count = 0;
	      foreach($this->roster as $user_id)
		{
		  $user = new user($this->dbpdo, $user_id, 'reddit_username');
		  echo ++$count . '. <a href="' . PREFIX . '/user/' . $user->value . '" style="color: black;">' . $user->value . '</a>';
		  try
		    {
		      echo ' <a href="http://www.reddit.com/message/compose/?to=' . $user->get_attribute_value('reddit_username',false) . '"><img src="' . PREFIX . '/images/reddit.png" style="border: 0; height: 1em;" /></a>';
		    }
		  catch(ObjectAttributeNotFoundException $e)
		    {

		    }
		  echo '<br />';
		}
	      if($count == 0)
		{
		  echo "<em>no students found</em>";
		}
	      */
	      $count = 0;
	      foreach($data as $user)
		{
		  echo ++$count . '. <a href="' . PREFIX . '/user/' . $user[0] . '" style="color: black;">' . $user[0] . '</a>';
		  if(isset($user[1]))
		    {
		      echo ' <a href="http://www.reddit.com/message/compose/?to=' . $user[1] . '"><img src="' . PREFIX . '/images/reddit.png" style="border: 0; height: 1em;" /></a>';
		    }
		  echo '<br />';
		}
	      if($count == 0)
		{
		  echo "<em>no students found</em>";
		}
	      ?>
	    </div>
	    <?php
	    }
	    ?>
	  </div>
	</div>
    <?php
  }

}

?>