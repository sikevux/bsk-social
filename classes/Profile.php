<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2011, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

/**
 * Table Definition for profile
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Profile extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile';                         // table name
    public $id;                              // int(4)  primary_key not_null
    public $nickname;                        // varchar(64)  multiple_key not_null
    public $fullname;                        // varchar(255)  multiple_key
    public $profileurl;                      // varchar(255)
    public $homepage;                        // varchar(255)  multiple_key
    public $bio;                             // text()  multiple_key
    public $location;                        // varchar(255)  multiple_key
    public $lat;                             // decimal(10,7)
    public $lon;                             // decimal(10,7)
    public $location_id;                     // int(4)
    public $location_ns;                     // int(4)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        $def = array(
            'description' => 'local and remote users have profiles',
            'fields' => array(
                'id' => array('type' => 'serial', 'not null' => true, 'description' => 'unique identifier'),
                'nickname' => array('type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'nickname or username', 'collate' => 'utf8_general_ci'),
                'fullname' => array('type' => 'varchar', 'length' => 255, 'description' => 'display name', 'collate' => 'utf8_general_ci'),
                'profileurl' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL, cached so we dont regenerate'),
                'homepage' => array('type' => 'varchar', 'length' => 255, 'description' => 'identifying URL', 'collate' => 'utf8_general_ci'),
                'bio' => array('type' => 'text', 'description' => 'descriptive biography', 'collate' => 'utf8_general_ci'),
                'location' => array('type' => 'varchar', 'length' => 255, 'description' => 'physical location', 'collate' => 'utf8_general_ci'),
                'lat' => array('type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'latitude'),
                'lon' => array('type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'longitude'),
                'location_id' => array('type' => 'int', 'description' => 'location id if possible'),
                'location_ns' => array('type' => 'int', 'description' => 'namespace for location'),

                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('id'),
            'indexes' => array(
                'profile_nickname_idx' => array('nickname'),
            )
        );

        // Add a fulltext index

        if (common_config('search', 'type') == 'fulltext') {
            $def['fulltext indexes'] = array('nickname' => array('nickname', 'fullname', 'location', 'bio', 'homepage'));
        }

        return $def;
    }
	
    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function getByEmail($email)
    {
        // in the future, profiles should have emails stored...
        $user = User::getKV('email', $email);
        if (!($user instanceof User)) {
            throw new NoSuchUserException(array('email'=>$email));
        }
        return $user->getProfile();
    } 

    protected $_user = -1;  // Uninitialized value distinct from null

    public function getUser()
    {
        if ($this->_user === -1) {
            $this->_user = User::getKV('id', $this->id);
        }
        if (!$this->_user instanceof User) {
            throw new NoSuchUserException(array('id'=>$this->id));
        }

        return $this->_user;
    }

    protected $_group = -1;

    public function getGroup()
    {
        if ($this->_group === -1) {
            $this->_group = User_group::getKV('profile_id', $this->id);
        }
        if (!$this->_group instanceof User_group) {
            throw new NoSuchGroupException(array('profile_id'=>$this->id));
        }

        return $this->_group;
    }

    public function isGroup()
    {
        try {
            $this->getGroup();
            return true;
        } catch (NoSuchGroupException $e) {
            return false;
        }
    }

    public function isLocal()
    {
        try {
            $this->getUser();
        } catch (NoSuchUserException $e) {
            return false;
        }
        return true;
    }

    protected $_avatars = array();

    public function getAvatar($width, $height=null)
    {
        return Avatar::byProfile($this, $width, $height);
    }

    public function setOriginal($filename)
    {
        $imagefile = new ImageFile($this->id, Avatar::path($filename));

        $avatar = new Avatar();
        $avatar->profile_id = $this->id;
        $avatar->width = $imagefile->width;
        $avatar->height = $imagefile->height;
        $avatar->mediatype = image_type_to_mime_type($imagefile->type);
        $avatar->filename = $filename;
        $avatar->original = true;
        $avatar->url = Avatar::url($filename);
        $avatar->created = common_sql_now();

        // XXX: start a transaction here
        if (!Avatar::deleteFromProfile($this, true) || !$avatar->insert()) {
            // If we can't delete the old avatars, let's abort right here.
            @unlink(Avatar::path($filename));
            return null;
        }

        foreach (array(AVATAR_PROFILE_SIZE, AVATAR_STREAM_SIZE, AVATAR_MINI_SIZE) as $size) {
            // We don't do a scaled one if original is our scaled size
            if (!($avatar->width == $size && $avatar->height == $size)) {
                try {
                    Avatar::newSize($this, $size);
                } catch (Exception $e) {
                    // should we abort the generation and live without smaller avatars?
                }
            }
        }

        return $avatar;
    }

    /**
     * Gets either the full name (if filled) or the nickname.
     *
     * @return string
     */
    function getBestName()
    {
        return ($this->fullname) ? $this->fullname : $this->nickname;
    }

    /**
     * Takes the currently scoped profile into account to give a name 
     * to list in notice streams. Preferences may differ between profiles.
     */
    function getStreamName()
    {
        $user = common_current_user();
        if ($user instanceof User && $user->streamNicknames()) {
            return $this->nickname;
        }

        return $this->getBestName();
    }

    /**
     * Gets the full name (if filled) with nickname as a parenthetical, or the nickname alone
     * if no fullname is provided.
     *
     * @return string
     */
    function getFancyName()
    {
        if ($this->fullname) {
            // TRANS: Full name of a profile or group (%1$s) followed by nickname (%2$s) in parentheses.
            return sprintf(_m('FANCYNAME','%1$s (%2$s)'), $this->fullname, $this->nickname);
        } else {
            return $this->nickname;
        }
    }

    /**
     * Get the most recent notice posted by this user, if any.
     *
     * @return mixed Notice or null
     */
    function getCurrentNotice()
    {
        $notice = $this->getNotices(0, 1);

        if ($notice->fetch()) {
            if ($notice instanceof ArrayWrapper) {
                // hack for things trying to work with single notices
                return $notice->_items[0];
            }
            return $notice;
        } else {
            return null;
        }
    }

    function getTaggedNotices($tag, $offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $max_id=0)
    {
        $stream = new TaggedProfileNoticeStream($this, $tag);

        return $stream->getNotices($offset, $limit, $since_id, $max_id);
    }

    function getNotices($offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $max_id=0, Profile $scoped=null)
    {
        $stream = new ProfileNoticeStream($this, $scoped);

        return $stream->getNotices($offset, $limit, $since_id, $max_id);
    }

    function isMember(User_group $group)
    {
    	$groups = $this->getGroups(0, null);
        while ($groups instanceof User_group && $groups->fetch()) {
    	    if ($groups->id == $group->id) {
    	        return true;
    	    }
    	}
    	return false;
    }

    function isAdmin(User_group $group)
    {
        $gm = Group_member::pkeyGet(array('profile_id' => $this->id,
                                          'group_id' => $group->id));
        return (!empty($gm) && $gm->is_admin);
    }

    function isPendingMember($group)
    {
        $request = Group_join_queue::pkeyGet(array('profile_id' => $this->id,
                                                   'group_id' => $group->id));
        return !empty($request);
    }

    function getGroups($offset=0, $limit=PROFILES_PER_PAGE)
    {
        $ids = array();

        $keypart = sprintf('profile:groups:%d', $this->id);

        $idstring = self::cacheGet($keypart);

        if ($idstring !== false) {
            $ids = explode(',', $idstring);
        } else {
            $gm = new Group_member();

            $gm->profile_id = $this->id;

            if ($gm->find()) {
                while ($gm->fetch()) {
                    $ids[] = $gm->group_id;
                }
            }

            self::cacheSet($keypart, implode(',', $ids));
        }

        if (!is_null($offset) && !is_null($limit)) {
            $ids = array_slice($ids, $offset, $limit);
        }

        try {
            return User_group::listFind('id', $ids);
        } catch (NoResultException $e) {
            return null;    // throw exception when we handle it everywhere
        }
    }

    function getGroupCount() {
        $groups = $this->getGroups(0, null);
        return $groups instanceof User_group
                ? $groups->N
                : 0;
    }

    function isTagged($peopletag)
    {
        $tag = Profile_tag::pkeyGet(array('tagger' => $peopletag->tagger,
                                          'tagged' => $this->id,
                                          'tag'    => $peopletag->tag));
        return !empty($tag);
    }

    function canTag($tagged)
    {
        if (empty($tagged)) {
            return false;
        }

        if ($tagged->id == $this->id) {
            return true;
        }

        $all = common_config('peopletag', 'allow_tagging', 'all');
        $local = common_config('peopletag', 'allow_tagging', 'local');
        $remote = common_config('peopletag', 'allow_tagging', 'remote');
        $subs = common_config('peopletag', 'allow_tagging', 'subs');

        if ($all) {
            return true;
        }

        $tagged_user = $tagged->getUser();
        if (!empty($tagged_user)) {
            if ($local) {
                return true;
            }
        } else if ($subs) {
            return (Subscription::exists($this, $tagged) ||
                    Subscription::exists($tagged, $this));
        } else if ($remote) {
            return true;
        }
        return false;
    }

    function getLists($auth_user, $offset=0, $limit=null, $since_id=0, $max_id=0)
    {
        $ids = array();

        $keypart = sprintf('profile:lists:%d', $this->id);

        $idstr = self::cacheGet($keypart);

        if ($idstr !== false) {
            $ids = explode(',', $idstr);
        } else {
            $list = new Profile_list();
            $list->selectAdd();
            $list->selectAdd('id');
            $list->tagger = $this->id;
            $list->selectAdd('id as "cursor"');

            if ($since_id>0) {
               $list->whereAdd('id > '.$since_id);
            }

            if ($max_id>0) {
                $list->whereAdd('id <= '.$max_id);
            }

            if($offset>=0 && !is_null($limit)) {
                $list->limit($offset, $limit);
            }

            $list->orderBy('id DESC');

            if ($list->find()) {
                while ($list->fetch()) {
                    $ids[] = $list->id;
                }
            }

            self::cacheSet($keypart, implode(',', $ids));
        }

        $showPrivate = (($auth_user instanceof User ||
                            $auth_user instanceof Profile) &&
                        $auth_user->id === $this->id);

        $lists = array();

        foreach ($ids as $id) {
            $list = Profile_list::getKV('id', $id);
            if (!empty($list) &&
                ($showPrivate || !$list->private)) {

                if (!isset($list->cursor)) {
                    $list->cursor = $list->id;
                }

                $lists[] = $list;
            }
        }

        return new ArrayWrapper($lists);
    }

    /**
     * Get tags that other people put on this profile, in reverse-chron order
     *
     * @param (Profile|User) $auth_user  Authorized user (used for privacy)
     * @param int            $offset     Offset from latest
     * @param int            $limit      Max number to get
     * @param datetime       $since_id   max date
     * @param datetime       $max_id     min date
     *
     * @return Profile_list resulting lists
     */

    function getOtherTags($auth_user=null, $offset=0, $limit=null, $since_id=0, $max_id=0)
    {
        $list = new Profile_list();

        $qry = sprintf('select profile_list.*, unix_timestamp(profile_tag.modified) as "cursor" ' .
                       'from profile_tag join profile_list '.
                       'on (profile_tag.tagger = profile_list.tagger ' .
                       '    and profile_tag.tag = profile_list.tag) ' .
                       'where profile_tag.tagged = %d ',
                       $this->id);


        if ($auth_user instanceof User || $auth_user instanceof Profile) {
            $qry .= sprintf('AND ( ( profile_list.private = false ) ' .
                            'OR ( profile_list.tagger = %d AND ' .
                            'profile_list.private = true ) )',
                            $auth_user->id);
        } else {
            $qry .= 'AND profile_list.private = 0 ';
        }

        if ($since_id > 0) {
            $qry .= sprintf('AND (cursor > %d) ', $since_id);
        }

        if ($max_id > 0) {
            $qry .= sprintf('AND (cursor < %d) ', $max_id);
        }

        $qry .= 'ORDER BY profile_tag.modified DESC ';

        if ($offset >= 0 && !is_null($limit)) {
            $qry .= sprintf('LIMIT %d OFFSET %d ', $limit, $offset);
        }

        $list->query($qry);
        return $list;
    }

    function getPrivateTags($offset=0, $limit=null, $since_id=0, $max_id=0)
    {
        $tags = new Profile_list();
        $tags->private = true;
        $tags->tagger = $this->id;

        if ($since_id>0) {
           $tags->whereAdd('id > '.$since_id);
        }

        if ($max_id>0) {
            $tags->whereAdd('id <= '.$max_id);
        }

        if($offset>=0 && !is_null($limit)) {
            $tags->limit($offset, $limit);
        }

        $tags->orderBy('id DESC');
        $tags->find();

        return $tags;
    }

    function hasLocalTags()
    {
        $tags = new Profile_tag();

        $tags->joinAdd(array('tagger', 'user:id'));
        $tags->whereAdd('tagged  = '.$this->id);
        $tags->whereAdd('tagger != '.$this->id);

        $tags->limit(0, 1);
        $tags->fetch();

        return ($tags->N == 0) ? false : true;
    }

    function getTagSubscriptions($offset=0, $limit=null, $since_id=0, $max_id=0)
    {
        $lists = new Profile_list();
        $subs = new Profile_tag_subscription();

        $lists->joinAdd(array('id', 'profile_tag_subscription:profile_tag_id'));

        #@fixme: postgres (round(date_part('epoch', my_date)))
        $lists->selectAdd('unix_timestamp(profile_tag_subscription.created) as "cursor"');

        $lists->whereAdd('profile_tag_subscription.profile_id = '.$this->id);

        if ($since_id>0) {
           $lists->whereAdd('cursor > '.$since_id);
        }

        if ($max_id>0) {
            $lists->whereAdd('cursor <= '.$max_id);
        }

        if($offset>=0 && !is_null($limit)) {
            $lists->limit($offset, $limit);
        }

        $lists->orderBy('"cursor" DESC');
        $lists->find();

        return $lists;
    }

    /**
     * Request to join the given group.
     * May throw exceptions on failure.
     *
     * @param User_group $group
     * @return mixed: Group_member on success, Group_join_queue if pending approval, null on some cancels?
     */
    function joinGroup(User_group $group)
    {
        $join = null;
        if ($group->join_policy == User_group::JOIN_POLICY_MODERATE) {
            $join = Group_join_queue::saveNew($this, $group);
        } else {
            if (Event::handle('StartJoinGroup', array($group, $this))) {
                $join = Group_member::join($group->id, $this->id);
                self::blow('profile:groups:%d', $this->id);
                self::blow('group:member_ids:%d', $group->id);
                self::blow('group:member_count:%d', $group->id);
                Event::handle('EndJoinGroup', array($group, $this));
            }
        }
        if ($join) {
            // Send any applicable notifications...
            $join->notify();
        }
        return $join;
    }

    /**
     * Leave a group that this profile is a member of.
     *
     * @param User_group $group
     */
    function leaveGroup(User_group $group)
    {
        if (Event::handle('StartLeaveGroup', array($group, $this))) {
            Group_member::leave($group->id, $this->id);
            self::blow('profile:groups:%d', $this->id);
            self::blow('group:member_ids:%d', $group->id);
            self::blow('group:member_count:%d', $group->id);
            Event::handle('EndLeaveGroup', array($group, $this));
        }
    }

    function avatarUrl($size=AVATAR_PROFILE_SIZE)
    {
        return Avatar::urlByProfile($this, $size);
    }

    function getSubscribed($offset=0, $limit=null)
    {
        $subs = Subscription::getSubscribedIDs($this->id, $offset, $limit);
        try {
            $profiles = Profile::listFind('id', $subs);
        } catch (NoResultException $e) {
            return $e->obj;
        }
        return $profiles;
    }

    function getSubscribers($offset=0, $limit=null)
    {
        $subs = Subscription::getSubscriberIDs($this->id, $offset, $limit);
        try {
            $profiles = Profile::listFind('id', $subs);
        } catch (NoResultException $e) {
            return $e->obj;
        }
        return $profiles;
    }

    function getTaggedSubscribers($tag, $offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN subscription ' .
          'ON profile.id = subscription.subscriber ' .
          'JOIN profile_tag ON (profile_tag.tagged = subscription.subscriber ' .
          'AND profile_tag.tagger = subscription.subscribed) ' .
          'WHERE subscription.subscribed = %d ' .
          "AND profile_tag.tag = '%s' " .
          'AND subscription.subscribed != subscription.subscriber ' .
          'ORDER BY subscription.created DESC ';

        if ($offset) {
            $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        }

        $profile = new Profile();

        $cnt = $profile->query(sprintf($qry, $this->id, $profile->escape($tag)));

        return $profile;
    }

    function getTaggedSubscriptions($tag, $offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN subscription ' .
          'ON profile.id = subscription.subscribed ' .
          'JOIN profile_tag on (profile_tag.tagged = subscription.subscribed ' .
          'AND profile_tag.tagger = subscription.subscriber) ' .
          'WHERE subscription.subscriber = %d ' .
          "AND profile_tag.tag = '%s' " .
          'AND subscription.subscribed != subscription.subscriber ' .
          'ORDER BY subscription.created DESC ';

        $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        $profile = new Profile();

        $profile->query(sprintf($qry, $this->id, $profile->escape($tag)));

        return $profile;
    }

    /**
     * Get pending subscribers, who have not yet been approved.
     *
     * @param int $offset
     * @param int $limit
     * @return Profile
     */
    function getRequests($offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN subscription_queue '.
          'ON profile.id = subscription_queue.subscriber ' .
          'WHERE subscription_queue.subscribed = %d ' .
          'ORDER BY subscription_queue.created DESC ';

        if ($limit != null) {
            if (common_config('db','type') == 'pgsql') {
                $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
            } else {
                $qry .= ' LIMIT ' . $offset . ', ' . $limit;
            }
        }

        $members = new Profile();

        $members->query(sprintf($qry, $this->id));
        return $members;
    }

    function subscriptionCount()
    {
        $c = Cache::instance();

        if (!empty($c)) {
            $cnt = $c->get(Cache::key('profile:subscription_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $sub = new Subscription();
        $sub->subscriber = $this->id;

        $cnt = (int) $sub->count('distinct subscribed');

        $cnt = ($cnt > 0) ? $cnt - 1 : $cnt;

        if (!empty($c)) {
            $c->set(Cache::key('profile:subscription_count:'.$this->id), $cnt);
        }

        return $cnt;
    }

    function subscriberCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $cnt = $c->get(Cache::key('profile:subscriber_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $sub = new Subscription();
        $sub->subscribed = $this->id;
        $sub->whereAdd('subscriber != subscribed');
        $cnt = (int) $sub->count('distinct subscriber');

        if (!empty($c)) {
            $c->set(Cache::key('profile:subscriber_count:'.$this->id), $cnt);
        }

        return $cnt;
    }

    /**
     * Is this profile subscribed to another profile?
     *
     * @param Profile $other
     * @return boolean
     */
    function isSubscribed(Profile $other)
    {
        return Subscription::exists($this, $other);
    }

    /**
     * Check if a pending subscription request is outstanding for this...
     *
     * @param Profile $other
     * @return boolean
     */
    function hasPendingSubscription($other)
    {
        return Subscription_queue::exists($this, $other);
    }

    /**
     * Are these two profiles subscribed to each other?
     *
     * @param Profile $other
     * @return boolean
     */
    function mutuallySubscribed($other)
    {
        return $this->isSubscribed($other) &&
          $other->isSubscribed($this);
    }

    function hasFave($notice)
    {
        $fave = Fave::pkeyGet(array('user_id' => $this->id,
                                    'notice_id' => $notice->id));
        return ((is_null($fave)) ? false : true);
    }

    function faveCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $cnt = $c->get(Cache::key('profile:fave_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $faves = new Fave();
        $faves->user_id = $this->id;
        $cnt = (int) $faves->count('notice_id');

        if (!empty($c)) {
            $c->set(Cache::key('profile:fave_count:'.$this->id), $cnt);
        }

        return $cnt;
    }

    function favoriteNotices($own=false, $offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $max_id=0)
    {
        return Fave::stream($this->id, $offset, $limit, $own, $since_id, $max_id);
    }

    function noticeCount()
    {
        $c = Cache::instance();

        if (!empty($c)) {
            $cnt = $c->get(Cache::key('profile:notice_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $notices = new Notice();
        $notices->profile_id = $this->id;
        $cnt = (int) $notices->count('distinct id');

        if (!empty($c)) {
            $c->set(Cache::key('profile:notice_count:'.$this->id), $cnt);
        }

        return $cnt;
    }

    function blowFavesCache()
    {
        $cache = Cache::instance();
        if ($cache) {
            // Faves don't happen chronologically, so we need to blow
            // ;last cache, too
            $cache->delete(Cache::key('fave:ids_by_user:'.$this->id));
            $cache->delete(Cache::key('fave:ids_by_user:'.$this->id.';last'));
            $cache->delete(Cache::key('fave:ids_by_user_own:'.$this->id));
            $cache->delete(Cache::key('fave:ids_by_user_own:'.$this->id.';last'));
        }
        $this->blowFaveCount();
    }

    function blowSubscriberCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $c->delete(Cache::key('profile:subscriber_count:'.$this->id));
        }
    }

    function blowSubscriptionCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $c->delete(Cache::key('profile:subscription_count:'.$this->id));
        }
    }

    function blowFaveCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $c->delete(Cache::key('profile:fave_count:'.$this->id));
        }
    }

    function blowNoticeCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $c->delete(Cache::key('profile:notice_count:'.$this->id));
        }
    }

    static function maxBio()
    {
        $biolimit = common_config('profile', 'biolimit');
        // null => use global limit (distinct from 0!)
        if (is_null($biolimit)) {
            $biolimit = common_config('site', 'textlimit');
        }
        return $biolimit;
    }

    static function bioTooLong($bio)
    {
        $biolimit = self::maxBio();
        return ($biolimit > 0 && !empty($bio) && (mb_strlen($bio) > $biolimit));
    }

    function update($dataObject=false)
    {
        if (is_object($dataObject) && $this->nickname != $dataObject->nickname) {
            try {
                $local = $this->getUser();
                common_debug("Updating User ({$this->id}) nickname from {$dataObject->nickname} to {$this->nickname}");
                $origuser = clone($local);
                $local->nickname = $this->nickname;
                $result = $local->updateKeys($origuser);
                if ($result === false) {
                    common_log_db_error($local, 'UPDATE', __FILE__);
                    // TRANS: Server error thrown when user profile settings could not be updated.
                    throw new ServerException(_('Could not update user nickname.'));
                }

                // Clear the site owner, in case nickname changed
                if ($local->hasRole(Profile_role::OWNER)) {
                    User::blow('user:site_owner');
                }
            } catch (NoSuchUserException $e) {
                // Nevermind...
            }
        }

        return parent::update($dataObject);
    }

    function delete($useWhere=false)
    {
        $this->_deleteNotices();
        $this->_deleteSubscriptions();
        $this->_deleteMessages();
        $this->_deleteTags();
        $this->_deleteBlocks();
        $this->_deleteAttentions();
        Avatar::deleteFromProfile($this, true);

        // Warning: delete() will run on the batch objects,
        // not on individual objects.
        $related = array('Reply',
                         'Group_member',
                         );
        Event::handle('ProfileDeleteRelated', array($this, &$related));

        foreach ($related as $cls) {
            $inst = new $cls();
            $inst->profile_id = $this->id;
            $inst->delete();
        }

        return parent::delete($useWhere);
    }

    function _deleteNotices()
    {
        $notice = new Notice();
        $notice->profile_id = $this->id;

        if ($notice->find()) {
            while ($notice->fetch()) {
                $other = clone($notice);
                $other->delete();
            }
        }
    }

    function _deleteSubscriptions()
    {
        $sub = new Subscription();
        $sub->subscriber = $this->id;

        $sub->find();

        while ($sub->fetch()) {
            $other = Profile::getKV('id', $sub->subscribed);
            if (empty($other)) {
                continue;
            }
            if ($other->id == $this->id) {
                continue;
            }
            Subscription::cancel($this, $other);
        }

        $subd = new Subscription();
        $subd->subscribed = $this->id;
        $subd->find();

        while ($subd->fetch()) {
            $other = Profile::getKV('id', $subd->subscriber);
            if (empty($other)) {
                continue;
            }
            if ($other->id == $this->id) {
                continue;
            }
            Subscription::cancel($other, $this);
        }

        $self = new Subscription();

        $self->subscriber = $this->id;
        $self->subscribed = $this->id;

        $self->delete();
    }

    function _deleteMessages()
    {
        $msg = new Message();
        $msg->from_profile = $this->id;
        $msg->delete();

        $msg = new Message();
        $msg->to_profile = $this->id;
        $msg->delete();
    }

    function _deleteTags()
    {
        $tag = new Profile_tag();
        $tag->tagged = $this->id;
        $tag->delete();
    }

    function _deleteBlocks()
    {
        $block = new Profile_block();
        $block->blocked = $this->id;
        $block->delete();

        $block = new Group_block();
        $block->blocked = $this->id;
        $block->delete();
    }

    function _deleteAttentions()
    {
        $att = new Attention();
        $att->profile_id = $this->getID();

        if ($att->find()) {
            while ($att->fetch()) {
                // Can't do delete() on the object directly since it won't remove all of it
                $other = clone($att);
                $other->delete();
            }
        }
    }

    // XXX: identical to Notice::getLocation.

    public function getLocation()
    {
        $location = null;

        if (!empty($this->location_id) && !empty($this->location_ns)) {
            $location = Location::fromId($this->location_id, $this->location_ns);
        }

        if (is_null($location)) { // no ID, or Location::fromId() failed
            if (!empty($this->lat) && !empty($this->lon)) {
                $location = Location::fromLatLon($this->lat, $this->lon);
            }
        }

        if (is_null($location)) { // still haven't found it!
            if (!empty($this->location)) {
                $location = Location::fromName($this->location);
            }
        }

        return $location;
    }

    public function shareLocation()
    {
        $cfg = common_config('location', 'share');

        if ($cfg == 'always') {
            return true;
        } else if ($cfg == 'never') {
            return false;
        } else { // user
            $share = common_config('location', 'sharedefault');

            // Check if user has a personal setting for this
            $prefs = User_location_prefs::getKV('user_id', $this->id);

            if (!empty($prefs)) {
                $share = $prefs->share_location;
                $prefs->free();
            }

            return $share;
        }
    }

    function hasRole($name)
    {
        $has_role = false;
        if (Event::handle('StartHasRole', array($this, $name, &$has_role))) {
            $role = Profile_role::pkeyGet(array('profile_id' => $this->id,
                                                'role' => $name));
            $has_role = !empty($role);
            Event::handle('EndHasRole', array($this, $name, $has_role));
        }
        return $has_role;
    }

    function grantRole($name)
    {
        if (Event::handle('StartGrantRole', array($this, $name))) {

            $role = new Profile_role();

            $role->profile_id = $this->id;
            $role->role       = $name;
            $role->created    = common_sql_now();

            $result = $role->insert();

            if (!$result) {
                throw new Exception("Can't save role '$name' for profile '{$this->id}'");
            }

            if ($name == 'owner') {
                User::blow('user:site_owner');
            }

            Event::handle('EndGrantRole', array($this, $name));
        }

        return $result;
    }

    function revokeRole($name)
    {
        if (Event::handle('StartRevokeRole', array($this, $name))) {

            $role = Profile_role::pkeyGet(array('profile_id' => $this->id,
                                                'role' => $name));

            if (empty($role)) {
                // TRANS: Exception thrown when trying to revoke an existing role for a user that does not exist.
                // TRANS: %1$s is the role name, %2$s is the user ID (number).
                throw new Exception(sprintf(_('Cannot revoke role "%1$s" for user #%2$d; does not exist.'),$name, $this->id));
            }

            $result = $role->delete();

            if (!$result) {
                common_log_db_error($role, 'DELETE', __FILE__);
                // TRANS: Exception thrown when trying to revoke a role for a user with a failing database query.
                // TRANS: %1$s is the role name, %2$s is the user ID (number).
                throw new Exception(sprintf(_('Cannot revoke role "%1$s" for user #%2$d; database error.'),$name, $this->id));
            }

            if ($name == 'owner') {
                User::blow('user:site_owner');
            }

            Event::handle('EndRevokeRole', array($this, $name));

            return true;
        }
    }

    function isSandboxed()
    {
        return $this->hasRole(Profile_role::SANDBOXED);
    }

    function isSilenced()
    {
        return $this->hasRole(Profile_role::SILENCED);
    }

    function sandbox()
    {
        $this->grantRole(Profile_role::SANDBOXED);
    }

    function unsandbox()
    {
        $this->revokeRole(Profile_role::SANDBOXED);
    }

    function silence()
    {
        $this->grantRole(Profile_role::SILENCED);
        if (common_config('notice', 'hidespam')) {
            $this->flushVisibility();
        }
    }

    function unsilence()
    {
        $this->revokeRole(Profile_role::SILENCED);
        if (common_config('notice', 'hidespam')) {
            $this->flushVisibility();
        }
    }

    function flushVisibility()
    {
        // Get all notices
        $stream = new ProfileNoticeStream($this, $this);
        $ids = $stream->getNoticeIds(0, CachingNoticeStream::CACHE_WINDOW);
        foreach ($ids as $id) {
            self::blow('notice:in-scope-for:%d:null', $id);
        }
    }

    /**
     * Does this user have the right to do X?
     *
     * With our role-based authorization, this is merely a lookup for whether the user
     * has a particular role. The implementation currently uses a switch statement
     * to determine if the user has the pre-defined role to exercise the right. Future
     * implementations may allow per-site roles, and different mappings of roles to rights.
     *
     * @param $right string Name of the right, usually a constant in class Right
     * @return boolean whether the user has the right in question
     */
    public function hasRight($right)
    {
        $result = false;

        if ($this->hasRole(Profile_role::DELETED)) {
            return false;
        }

        if (Event::handle('UserRightsCheck', array($this, $right, &$result))) {
            switch ($right)
            {
            case Right::DELETEOTHERSNOTICE:
            case Right::MAKEGROUPADMIN:
            case Right::SANDBOXUSER:
            case Right::SILENCEUSER:
            case Right::DELETEUSER:
            case Right::DELETEGROUP:
            case Right::TRAINSPAM:
            case Right::REVIEWSPAM:
                $result = $this->hasRole(Profile_role::MODERATOR);
                break;
            case Right::CONFIGURESITE:
                $result = $this->hasRole(Profile_role::ADMINISTRATOR);
                break;
            case Right::GRANTROLE:
            case Right::REVOKEROLE:
                $result = $this->hasRole(Profile_role::OWNER);
                break;
            case Right::NEWNOTICE:
            case Right::NEWMESSAGE:
            case Right::SUBSCRIBE:
            case Right::CREATEGROUP:
                $result = !$this->isSilenced();
                break;
            case Right::PUBLICNOTICE:
            case Right::EMAILONREPLY:
            case Right::EMAILONSUBSCRIBE:
            case Right::EMAILONFAVE:
                $result = !$this->isSandboxed();
                break;
            case Right::WEBLOGIN:
                $result = !$this->isSilenced();
                break;
            case Right::API:
                $result = !$this->isSilenced();
                break;
            case Right::BACKUPACCOUNT:
                $result = common_config('profile', 'backup');
                break;
            case Right::RESTOREACCOUNT:
                $result = common_config('profile', 'restore');
                break;
            case Right::DELETEACCOUNT:
                $result = common_config('profile', 'delete');
                break;
            case Right::MOVEACCOUNT:
                $result = common_config('profile', 'move');
                break;
            default:
                $result = false;
                break;
            }
        }
        return $result;
    }

    // FIXME: Can't put Notice typing here due to ArrayWrapper
    public function hasRepeated($notice)
    {
        // XXX: not really a pkey, but should work

        $notice = Notice::pkeyGet(array('profile_id' => $this->id,
                                        'repeat_of' => $notice->id));

        return !empty($notice);
    }

    /**
     * Returns an XML string fragment with limited profile information
     * as an Atom <author> element.
     *
     * Assumes that Atom has been previously set up as the base namespace.
     *
     * @param Profile $cur the current authenticated user
     *
     * @return string
     */
    function asAtomAuthor($cur = null)
    {
        $xs = new XMLStringer(true);

        $xs->elementStart('author');
        $xs->element('name', null, $this->nickname);
        $xs->element('uri', null, $this->getUri());
        if ($cur != null) {
            $attrs = Array();
            $attrs['following'] = $cur->isSubscribed($this) ? 'true' : 'false';
            $attrs['blocking']  = $cur->hasBlocked($this) ? 'true' : 'false';
            $xs->element('statusnet:profile_info', $attrs, null);
        }
        $xs->elementEnd('author');

        return $xs->getString();
    }

    /**
     * Extra profile info for atom entries
     *
     * Clients use some extra profile info in the atom stream.
     * This gives it to them.
     *
     * @param User $cur Current user
     *
     * @return array representation of <statusnet:profile_info> element or null
     */

    function profileInfo($cur)
    {
        $profileInfoAttr = array('local_id' => $this->id);

        if ($cur != null) {
            // Whether the current user is a subscribed to this profile
            $profileInfoAttr['following'] = $cur->isSubscribed($this) ? 'true' : 'false';
            // Whether the current user is has blocked this profile
            $profileInfoAttr['blocking']  = $cur->hasBlocked($this) ? 'true' : 'false';
        }

        return array('statusnet:profile_info', $profileInfoAttr, null);
    }

    /**
     * Returns an XML string fragment with profile information as an
     * Activity Streams <activity:actor> element.
     *
     * Assumes that 'activity' namespace has been previously defined.
     *
     * @return string
     */
    function asActivityActor()
    {
        return $this->asActivityNoun('actor');
    }

    /**
     * Returns an XML string fragment with profile information as an
     * Activity Streams noun object with the given element type.
     *
     * Assumes that 'activity', 'georss', and 'poco' namespace has been
     * previously defined.
     *
     * @param string $element one of 'actor', 'subject', 'object', 'target'
     *
     * @return string
     */
    function asActivityNoun($element)
    {
        $noun = ActivityObject::fromProfile($this);
        return $noun->asString('activity:' . $element);
    }

    /**
     * Returns the profile's canonical url, not necessarily a uri/unique id
     *
     * @return string $profileurl
     */
    public function getUrl()
    {
        if (empty($this->profileurl) ||
                !filter_var($this->profileurl, FILTER_VALIDATE_URL)) {
            throw new InvalidUrlException($this->profileurl);
        }
        return $this->profileurl;
    }

    /**
     * Returns the best URI for a profile. Plugins may override.
     *
     * @return string $uri
     */
    public function getUri()
    {
        $uri = null;

        // give plugins a chance to set the URI
        if (Event::handle('StartGetProfileUri', array($this, &$uri))) {

            // check for a local user first
            $user = User::getKV('id', $this->id);

            if (!empty($user)) {
                $uri = $user->uri;
            }

            Event::handle('EndGetProfileUri', array($this, &$uri));
        }

        return $uri;
    }

    /**
     * Returns an assumed acct: URI for a profile. Plugins are required.
     *
     * @return string $uri
     */
    public function getAcctUri()
    {
        $acct = null;

        if (Event::handle('StartGetProfileAcctUri', array($this, &$acct))) {
            Event::handle('EndGetProfileAcctUri', array($this, &$acct));
        }

        if ($acct === null) {
            throw new ProfileNoAcctUriException($this);
        }

        return $acct;
    }

    function hasBlocked($other)
    {
        $block = Profile_block::exists($this, $other);
        return !empty($block);
    }

    function getAtomFeed()
    {
        $feed = null;

        if (Event::handle('StartProfileGetAtomFeed', array($this, &$feed))) {
            $user = User::getKV('id', $this->id);
            if (!empty($user)) {
                $feed = common_local_url('ApiTimelineUser', array('id' => $user->id,
                                                                  'format' => 'atom'));
            }
            Event::handle('EndProfileGetAtomFeed', array($this, $feed));
        }

        return $feed;
    }

    static function fromURI($uri)
    {
        $profile = null;

        if (Event::handle('StartGetProfileFromURI', array($uri, &$profile))) {
            // Get a local user
            $user = User::getKV('uri', $uri);
            if (!empty($user)) {
                $profile = $user->getProfile();
            }
            Event::handle('EndGetProfileFromURI', array($uri, $profile));
        }

        return $profile;
    }

    function canRead(Notice $notice)
    {
        if ($notice->scope & Notice::SITE_SCOPE) {
            $user = $this->getUser();
            if (empty($user)) {
                return false;
            }
        }

        if ($notice->scope & Notice::ADDRESSEE_SCOPE) {
            $replies = $notice->getReplies();

            if (!in_array($this->id, $replies)) {
                $groups = $notice->getGroups();

                $foundOne = false;

                foreach ($groups as $group) {
                    if ($this->isMember($group)) {
                        $foundOne = true;
                        break;
                    }
                }

                if (!$foundOne) {
                    return false;
                }
            }
        }

        if ($notice->scope & Notice::FOLLOWER_SCOPE) {
            $author = $notice->getProfile();
            if (!Subscription::exists($this, $author)) {
                return false;
            }
        }

        return true;
    }

    static function current()
    {
        $user = common_current_user();
        if (empty($user)) {
            $profile = null;
        } else {
            $profile = $user->getProfile();
        }
        return $profile;
    }

    /**
     * Magic function called at serialize() time.
     *
     * We use this to drop a couple process-specific references
     * from DB_DataObject which can cause trouble in future
     * processes.
     *
     * @return array of variable names to include in serialization.
     */

    function __sleep()
    {
        $vars = parent::__sleep();
        $skip = array('_user', '_avatars');
        return array_diff($vars, $skip);
    }

    public function getProfile()
    {
        return $this;
    }
}
