<?php

// TODO: move the post-validation cleanup of the slug into the
// validator so that we don't get a user-unfriendly error or
// failure when /Slug Foo fails to be considered a duplicate
// of /slug_foo the first time around

class BaseaPageSettingsForm extends aPageForm
{
  // Use this to i18n select choices that SHOULD be i18ned and other things that the
  // sniffer would otherwise miss. It never gets called, it's just here for our i18n-update 
  // task to sniff. Don't worry about widget labels or validator error messages,
  // the sniffer is smart about those
  private function i18nDummy()
  {
    __('Choose a User to Add', null, 'apostrophe');
    __('Home Page', null, 'apostrophe');
    __('Default Page', null, 'apostrophe');
    __('Template-Based', null, 'apostrophe');
    __('Media', null, 'apostrophe');
    __('Published', null, 'apostrophe');
    __('Unpublished', null, 'apostrophe');
    __('results', null, 'apostrophe');    
    __('Login Required', null, 'apostrophe');
  }
  
  public function configure()
  {
    parent::configure();
    
    // We must explicitly limit the fields because otherwise tables with foreign key relationships
    // to the pages table will extend the form whether it's appropriate or not. If you want to do
    // those things on behalf of an engine used in some pages, define a form class called
    // enginemodulenameEngineForm. It will automatically be instantiated with the engine page
    // as an argument to the constructor, and rendered beneath the main page settings form.
    // On submit, it will be bound to the parameter name that begins its name format and, if valid,
    // saved consecutively after the main page settings form. The form will be rendered via
    // the _renderPageSettingsForm partial in your engine module, which must exist, although it
    // can be as simple as echo $form. (Your form is passed to the partial as $form.)
    // 
    // We would use embedded forms if we could. Unfortunately Symfony has unresolved bugs relating
    // to one-to-many relations in embedded forms.
    
    $this->useFields(array('slug', 'template', 'engine', 'archived', 'view_is_secure'));
    
    unset(
      $this['author_id'],
      $this['deleter_id'],
      $this['Accesses'],
      $this['created_at'],
      $this['updated_at'],
      $this['view_credentials'],
      $this['edit_credentials'],
      $this['lft'],
      $this['rgt'],
      $this['level']
    );

    $this->setWidget('template', new sfWidgetFormSelect(array('choices' => aTools::getTemplates())));
     
    $this->setWidget('engine', new sfWidgetFormSelect(array('choices' => aTools::getEngines())));

    // On vs. off makes more sense to end users, but when we first
    // designed this feature we had an 'archived vs. unarchived'
    // approach in mind
    $this->setWidget('archived', new sfWidgetFormChoice(array(
      'expanded' => true,
      'choices' => array(false => "Published", true => "Unpublished"),
      'default' => false
    )));

    if ($this->getObject()->hasChildren(false))
    {
      $this->setWidget('cascade_archived', new sfWidgetFormInputCheckbox());
      $this->setValidator('cascade_archived', new sfValidatorBoolean(array(
        'true_values' =>  array('true', 't', 'on', '1'),
        'false_values' => array('false', 'f', 'off', '0', ' ', '')
      )));
      $this->setWidget('cascade_view_is_secure', new sfWidgetFormInputCheckbox());
      $this->setValidator('cascade_view_is_secure', new sfValidatorBoolean(array(
        'true_values' =>  array('true', 't', 'on', '1'),
        'false_values' => array('false', 'f', 'off', '0', ' ', '')
      )));
    }

    $this->setWidget('view_is_secure', new sfWidgetFormChoice(array(
      'expanded' => true,
      'choices' => array(
        false => "Public",
        true => "Login Required"
      ),
      'default' => false
    )));

	// Tags
	$tagstring = implode(', ', $this->getObject()->getTags());  // added a space after the comma for readability
	// class tag-input enabled for typeahead support
	$this->setWidget('tags', new sfWidgetFormInput(array('default' => $tagstring), array('class' => 'tags-input')));
	$this->setValidator('tags', new sfValidatorString(array('required' => false)));


	// Meta Description
	$metaDescription = $this->getObject()->getMetaDescription();
	$this->setWidget('meta_description', new sfWidgetFormTextArea(array('default' => html_entity_decode($metaDescription, ENT_COMPAT, 'UTF-8'))));
	$this->setValidator('meta_description', new sfValidatorString(array('required' => false)));



    $this->addPrivilegeWidget('edit', 'editors');
    $this->addPrivilegeWidget('manage', 'managers');
    $this->addGroupPrivilegeWidget('edit', 'group_editors');
    $this->addGroupPrivilegeWidget('manage', 'group_managers');
    
    // If you can delete the page, you can change the slug
    if ($this->getObject()->userHasPrivilege('manage'))
    {
      $this->setValidator('slug', new aValidatorSlug(array('required' => true, 'allow_slashes' => true, 'require_leading_slash' => true), array('required' => 'The slug cannot be empty.',
          'invalid' => 'The slug must contain only slashes, letters, digits, dashes and underscores. There must be a leading slash. Also, you cannot change a slug to conflict with an existing slug.')));
    	$this->setWidget('slug', new sfWidgetFormInputText());
	  }

    // Named 'realtitle' to avoid excessively magic Doctrine form behavior.
    // Unfortunately no amount of care will allow us to make &lt; appear in 
    // a title (as opposed to a < ) due to Symfony's hard override of 
    // double escaping. Fortunately, that's not a likely thing to want in a title
    
    $this->setValidator('realtitle', new sfValidatorString(array('required' => true), array('required' => 'The title cannot be empty.')));

    $title = $this->getObject()->getTitle();
		$this->setWidget('realtitle', new sfWidgetFormInputText(array('default' => html_entity_decode($this->getObject()->getTitle(), ENT_COMPAT, 'UTF-8'))));
		
    $this->setValidator('template', new sfValidatorChoice(array(
      'required' => true,
      'choices' => array_keys(aTools::getTemplates())
    )));

    // Making the empty string one of the choices doesn't seem to be good enough
    // unless we expressly clear 'required'
    $this->setValidator('engine', new sfValidatorChoice(array(
      'required' => false,
      'choices' => array_keys(aTools::getEngines())
    )));   

    // The slug of the home page cannot change (chicken and egg problems)
    if ($this->getObject()->getSlug() === '/')
    {
      unset($this['slug']);
    }
    else
    {
      $this->validatorSchema->setPostValidator(new sfValidatorDoctrineUnique(array(
        'model' => 'aPage',
        'column' => 'slug'
      ), array('invalid' => 'There is already a page with that slug.')));
    }
    
    $this->widgetSchema->setIdFormat('a_settings_%s');
    $this->widgetSchema->setNameFormat('settings[%s]');
    $this->widgetSchema->setFormFormatterName('list');

    $user = sfContext::getInstance()->getUser();
    if (!$user->hasCredential('cms_admin'))
    {
      unset($this['editors']);
      unset($this['managers']);
      unset($this['group_editors']);
      unset($this['group_managers']);
    }
    // We changed the form formatter name, so we have to reset the translation catalogue too 
    $this->widgetSchema->getFormFormatter()->setTranslationCatalogue('apostrophe');
  }
  
  protected function addPrivilegeWidget($privilege, $widgetName)
  {
    // For i18n-update we need to tolerate being run without a proper page
    if ($this->getObject()->isNew())
    {
      $all = array();
      $selected = array();
      $inherited = array();
      $sufficient = array();
    }
    else
    {
      list($all, $selected, $inherited, $sufficient) = $this->getObject()->getAccessesById($privilege);
    }
    foreach ($inherited as $userId)
    {
      unset($all[$userId]);
    }

    foreach ($sufficient as $userId)
    {
      unset($all[$userId]);
    }

    $this->setWidget($widgetName, new sfWidgetFormSelect(array(
      // + operator is correct: we don't want renumbering when
      // ids are numeric
      'choices' => $all,
      'multiple' => true,
      'default' => $selected
    )));

    $this->setValidator($widgetName, new sfValidatorChoice(array(
      'required' => false, 
      'multiple' => true,
      'choices' => array_keys($all)
    )));
  }
  
  protected function addGroupPrivilegeWidget($privilege, $widgetName)
  {
    // For i18n-update we need to tolerate being run without a proper page
    if ($this->getObject()->isNew())
    {
      $all = array();
      $selected = array();
      $inherited = array();
    }
    else
    {
      list($all, $selected, $inherited) = $this->getObject()->getGroupAccessesById($privilege);
    }
    foreach ($inherited as $userId)
    {
      unset($all[$userId]);
    }

    $this->setWidget($widgetName, new sfWidgetFormSelect(array(
      'choices' => $all,
      'multiple' => true,
      'default' => $selected
    )));

    $this->setValidator($widgetName, new sfValidatorChoice(array(
      'required' => false, 
      'multiple' => true,
      'choices' => array_keys($all)
    )));
  }

  public function updateObject($values = null)
  {
    if (is_null($values))
    {
      $values = $this->getValues();
    }
    $oldSlug = $this->getObject()->slug;
    $object = parent::updateObject($values);
    
    // Update tags on Page
    if ($this->getValue('tags') != '')
    {
	    $this->getObject()->addTag($this->getValue('tags'));
	}

    // Update meta-description on Page
    if ($this->getValue('meta_description') != '')
    {
	    $this->getObject()->setMetaDescription(htmlentities($this->getValue('meta_description')));
	}    
    
    // Check for cascading operations
    if($this->getValue('cascade_archived') || $this->getValue('cascade_view_is_secure'))
    {
      $q = Doctrine::getTable('aPage')->createQuery()
        ->update()
        ->where('lft > ? and rgt < ?', array($object->getLft(), $object->getRgt()));
      if($this->getValue('cascade_archived'))
      {
        $q->set('archived', '?', $object->getArchived());
      }
      if($this->getValue('cascade_view_is_secure'))
      {
        $q->set('view_is_secure', '?', $object->getViewIsSecure());
      }
      $q->execute();
    }

    // On manual change of slug, set up a redirect from the old slug,
    // and notify child pages so they can update their slugs if they are
    // not already deliberately different
    if ($object->slug !== $oldSlug)
    {
      Doctrine::getTable('aRedirect')->update($oldSlug, $object);
      $children = $object->getChildren();
      foreach ($children as $child)
      {
        $child->updateParentSlug($oldSlug, $object->slug);
      }
    }
    
    if (isset($object->engine) && (!strlen($object->engine)))
    {
      // Store it as null for plain ol' executeShow page templating
      $object->engine = null;
    }
    $this->savePrivileges($object, 'edit', 'editors');
    $this->savePrivileges($object, 'manage', 'managers');
    $this->saveGroupPrivileges($object, 'edit', 'group_editors');
    $this->saveGroupPrivileges($object, 'manage', 'group_managers');
    
        
    $this->getObject()->setTitle(htmlentities($values['realtitle'], ENT_COMPAT, 'UTF-8'));
    
    
    // Has to be done on shutdown so it comes after the in-memory cache of
    // sfFileCache copies itself back to disk, which otherwise overwrites
    // our attempt to invalidate the routing cache [groan]
    register_shutdown_function(array($this, 'invalidateRoutingCache'));
  }
  
  public function invalidateRoutingCache()
  {
    // Clear the routing cache on page settings changes. TODO:
    // finesse this to happen only when the engine is changed,
    // and then perhaps further to clear only cache entries
    // relating to this page
    $routing = sfContext::getInstance()->getRouting();
    if ($routing)
    {
      $cache = $routing->getCache();
      if ($cache)
      {
        $cache->clean();
      }
    }
  }
  
  protected function savePrivileges($object, $privilege, $widgetName)
  {
    if (isset($this[$widgetName]))
    {
      $editorIds = $this->getValue($widgetName);
      // Happens when the list is empty (sigh)
      if ($editorIds === null)
      {
        $editorIds = array();
      }
      
      $object->setAccessesById($privilege, $editorIds);
    }
  }  
  
  protected function saveGroupPrivileges($object, $privilege, $widgetName)
  {
    if (isset($this[$widgetName]))
    {
      $editorIds = $this->getValue($widgetName);
      // Happens when the list is empty (sigh)
      if ($editorIds === null)
      {
        $editorIds = array();
      }
      $object->setGroupAccessesById($privilege, $editorIds);
    }
  }
}
