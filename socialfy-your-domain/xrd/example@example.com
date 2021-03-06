<?xml version="1.0" encoding="UTF-8"?>
<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0">
  <Subject>acct:example@example.com</Subject>
  <Alias>acct:example@social.example.com</Alias>
  <Alias>http://social.example.com/user/1</Alias>
  <Link rel="http://webfinger.net/rel/profile-page" type="text/html" href="http://social.example.com/user/1"/>
  <Link rel="http://schemas.google.com/g/2010#updates-from" type="application/atom+xml" href="http://social.example.com/api/statuses/user_timeline/1.atom"/>
  <Link rel="http://microformats.org/profile/hcard" type="text/html" href="http://social.example.com/hcard"/>
  <Link rel="http://gmpg.org/xfn/11" type="text/html" href="http://social.example.com/user/1"/>
  <Link rel="describedby" type="application/rdf+xml" href="http://social.example.com/foaf"/>
  <Link rel="http://salmon-protocol.org/ns/salmon-replies" href="http://social.example.com/main/salmon/user/1"/>
  <Link rel="http://salmon-protocol.org/ns/salmon-mention" href="http://social.example.com/main/salmon/user/1"/>
  <Link rel="http://ostatus.org/schema/1.0/subscribe" template="http://social.example.com/main/ostatussub?profile={uri}"/>
</XRD>
