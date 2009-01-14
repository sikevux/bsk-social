<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Low-level generator for HTML
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Output
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Sarven Capadisli <csarven@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/xmloutputter.php';

define('PAGE_TYPE_PREFS',
       'text/html,application/xhtml+xml,'.
       'application/xml;q=0.3,text/xml;q=0.2');

/**
 * Low-level generator for HTML
 *
 * Abstracts some of the code necessary for HTML generation. Especially
 * has methods for generating HTML form elements. Note that these have
 * been created kind of haphazardly, not with an eye to making a general
 * HTML-creation class.
 *
 * @category Output
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      Action
 * @see      XMLOutputter
 */

class HTMLOutputter extends XMLOutputter
{
    /**
     * Constructor
     *
     * Just wraps the XMLOutputter constructor.
     *
     * @param string  $output URI to output to, default = stdout
     * @param boolean $indent Whether to indent output, default true
     */

    function __construct($output='php://output', $indent=true)
    {
        parent::__construct($output, $indent);
    }

    /**
     * Start an HTML document
     *
     * If $type isn't specified, will attempt to do content negotiation.
     *
     * Attempts to do content negotiation for language, also.
     *
     * @param string $type MIME type to use; default is to do negotation.
     *
     * @todo extract content negotiation code to an HTTP module or class.
     *
     * @return void
     */

    function startHTML($type=null)
    {
        if (!$type) {
            $httpaccept = isset($_SERVER['HTTP_ACCEPT']) ?
              $_SERVER['HTTP_ACCEPT'] : null;

            // XXX: allow content negotiation for RDF, RSS, or XRDS

            $cp = common_accept_to_prefs($httpaccept);
            $sp = common_accept_to_prefs(PAGE_TYPE_PREFS);

            $type = common_negotiate_type($cp, $sp);

            if (!$type) {
                common_user_error(_('This page is not available in a '.
                                    'media type you accept'), 406);
                exit(0);
            }
        }

        header('Content-Type: '.$type);

        $this->startXML('html',
                        '-//W3C//DTD XHTML 1.0 Strict//EN',
                        'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd');

        // FIXME: correct language for interface

        $language = common_language();

        $this->elementStart('html', array('xmlns' => 'http://www.w3.org/1999/xhtml',
                                          'xml:lang' => $language,
                                          'lang' => $language));
    }

    /**
     * Output an HTML text input element
     *
     * Despite the name, it is specifically for outputting a
     * text input element, not other <input> elements. It outputs
     * a cluster of elements, including a <label> and an associated
     * instructions span.
     *
     * @param string $id           element ID, must be unique on page
     * @param string $label        text of label for the element
     * @param string $value        value of the element, default null
     * @param string $instructions instructions for valid input
     *
     * @todo add a $name parameter
     * @todo add a $maxLength parameter
     * @todo add a $size parameter
     *
     * @return void
     */

    function input($id, $label, $value=null, $instructions=null)
    {
        $this->elementStart('p');
        $this->element('label', array('for' => $id), $label);
        $attrs = array('name' => $id,
                       'type' => 'text',
                       'class' => 'input_text',
                       'id' => $id);
        if ($value) {
            $attrs['value'] = htmlspecialchars($value);
        }
        $this->element('input', $attrs);
        if ($instructions) {
            $this->element('span', 'input_instructions', $instructions);
        }
        $this->elementEnd('p');
    }

    /**
     * output an HTML checkbox and associated elements
     *
     * Note that the value is default 'true' (the string), which can
     * be used by Action::boolean()
     *
     * @param string $id           element ID, must be unique on page
     * @param string $label        text of label for the element
     * @param string $checked      if the box is checked, default false
     * @param string $instructions instructions for valid input
     * @param string $value        value of the checkbox, default 'true'
     * @param string $disabled     show the checkbox disabled, default false
     *
     * @return void
     *
     * @todo add a $name parameter
     */

    function checkbox($id, $label, $checked=false, $instructions=null,
                      $value='true', $disabled=false)
    {
        $this->elementStart('p');
        $attrs = array('name' => $id,
                       'type' => 'checkbox',
                       'class' => 'checkbox',
                       'id' => $id);
        if ($value) {
            $attrs['value'] = htmlspecialchars($value);
        }
        if ($checked) {
            $attrs['checked'] = 'checked';
        }
        if ($disabled) {
            $attrs['disabled'] = 'true';
        }
        $this->element('input', $attrs);
        $this->text(' ');
        $this->element('label', array('class' => 'checkbox_label',
                                      'for' => $id),
                       $label);
        $this->text(' ');
        if ($instructions) {
            $this->element('span', 'input_instructions', $instructions);
        }
        $this->elementEnd('p');
    }

    /**
     * output an HTML combobox/select and associated elements
     *
     * $content is an array of key-value pairs for the dropdown, where
     * the key is the option value attribute and the value is the option
     * text. (Careful on the overuse of 'value' here.)
     *
     * @param string $id           element ID, must be unique on page
     * @param string $label        text of label for the element
     * @param array  $content      options array, value => text
     * @param string $instructions instructions for valid input
     * @param string $blank_select whether to have a blank entry, default false
     * @param string $selected     selected value, default null
     *
     * @return void
     *
     * @todo add a $name parameter
     */

    function dropdown($id, $label, $content, $instructions=null,
                      $blank_select=false, $selected=null)
    {
        $this->elementStart('p');
        $this->element('label', array('for' => $id), $label);
        $this->elementStart('select', array('id' => $id, 'name' => $id));
        if ($blank_select) {
            $this->element('option', array('value' => ''));
        }
        foreach ($content as $value => $option) {
            if ($value == $selected) {
                $this->element('option', array('value' => $value,
                                               'selected' => $value),
                               $option);
            } else {
                $this->element('option', array('value' => $value), $option);
            }
        }
        $this->elementEnd('select');
        if ($instructions) {
            $this->element('span', 'input_instructions', $instructions);
        }
        $this->elementEnd('p');
    }

    /**
     * output an HTML hidden element
     *
     * $id is re-used as name
     *
     * @param string $id    element ID, must be unique on page
     * @param string $value hidden element value, default null
     * @param string $name  name, if different than ID
     *
     * @return void
     */

    function hidden($id, $value, $name=null)
    {
        $this->element('input', array('name' => ($name) ? $name : $id,
                                      'type' => 'hidden',
                                      'id' => $id,
                                      'value' => $value));
    }

    /**
     * output an HTML password input and associated elements
     *
     * @param string $id           element ID, must be unique on page
     * @param string $label        text of label for the element
     * @param string $instructions instructions for valid input
     *
     * @return void
     *
     * @todo add a $name parameter
     */

    function password($id, $label, $instructions=null)
    {
        $this->elementStart('p');
        $this->element('label', array('for' => $id), $label);
        $attrs = array('name' => $id,
                       'type' => 'password',
                       'class' => 'password',
                       'id' => $id);
        $this->element('input', $attrs);
        if ($instructions) {
            $this->element('span', 'input_instructions', $instructions);
        }
        $this->elementEnd('p');
    }

    /**
     * output an HTML submit input and associated elements
     *
     * @param string $id    element ID, must be unique on page
     * @param string $label text of the button
     * @param string $cls   class of the button, default 'submit'
     * @param string $name  name, if different than ID
     *
     * @return void
     *
     * @todo add a $name parameter
     */

    function submit($id, $label, $cls='submit', $name=null)
    {
        $this->elementStart('p');
        $this->element('input', array('type' => 'submit',
                                      'id' => $id,
                                      'name' => ($name) ? $name : $id,
                                      'class' => $cls,
                                      'value' => $label));
        $this->elementEnd('p');
    }

    /**
     * output an HTML textarea and associated elements
     *
     * @param string $id           element ID, must be unique on page
     * @param string $label        text of label for the element
     * @param string $content      content of the textarea, default none
     * @param string $instructions instructions for valid input
     *
     * @return void
     *
     * @todo add a $name parameter
     * @todo add a $cols parameter
     * @todo add a $rows parameter
     */

    function textarea($id, $label, $content=null, $instructions=null)
    {
        $this->elementStart('p');
        $this->element('label', array('for' => $id), $label);
        $this->element('textarea', array('rows' => 3,
                                         'cols' => 40,
                                         'name' => $id,
                                         'id' => $id),
                       ($content) ? $content : '');
        if ($instructions) {
            $this->element('span', 'input_instructions', $instructions);
        }
        $this->elementEnd('p');
    }
}
