<?php

/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
abstract class PluginaRichTextSlot extends BaseaRichTextSlot
{
  protected $editDefault = true;
  
  public function getSearchText()
  {
    // Convert from HTML to plaintext before indexing by Lucene
    
    // However first add line breaks after certain tags for better diff results
    // (this method is also used for generating informational diffs between versions).
    // This is a noncritical feature so it doesn't have to be as precise
    // as strip_tags and shouldn't try to substitute for it in the matter of 
    // actually removing the tags
    $this->value = preg_replace("/(<p>|<br.*?>|<blockquote>|<li>|<dt>|<dd>|<nl>|<ol>)/i", "$1\n", $this->value);
    
    return strip_tags($this->value);
  }

  /**
   * Returns the plaintext representation of this slot
   */
  public function getText()
  {
    return $this->getSearchText();
  }
  
  /**
   * This function returns a basic HTML representation of your slot's comments
   * (passing the default settings of aHtml::simplify, for instance). Used for Google Calendar
   * buttons, RSS feeds and similar
   * @return string
   */
  public function getBasicHtml()
  {
    /* 
      Already cleaned by aHtml::simplify
    */
    return $this->value;
  }
}
