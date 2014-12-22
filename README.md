<h1>Resty 1.0.1</h1>
<p>Publish a complete REST API from any MySQL database with authentication, security, and virtual tables built-in.</p>

<h3>Security Model</h3>
<p>Resty uses the concept of <em>authority</em> to deny/accept access and affect actions on resources and fields.</p>

<h5>Authority</h5>
<p>When a resource is accessed or affected, the authorised user of the request is compared to the <em>authority user</em> of the resource. The authority user of a resource is designated by any reference field that has an <em>authority link</em> to a user in the user's table. Authority links are the series of reference fields that link a resource eventually to the user's table, and to an authority user. An authority link could be a direct reference to a user, or it could traverse multiple tables.</p>
<pre><strong>Eg:</strong> Book > Library > Librarian
  // Each book has an authority field linking to a
  // library which has an authority field linking to
  // a librarian, who therefore is the authority of
  // the book</pre>
<p>Reference fields which denote an authority link can be specified in the field's meta:</p>
<pre>{"authority": true}</pre>

<h5></h5>
<p>Before a resource is written, read, or deleted, the relationships between the current user and the resource is determined. This can result in 5 possible relationships:</p>
<ul>
  <li><strong>None</strong> - The resource has no relationship to the user
  <li><strong>Private</strong> - The resource is owned by the current user</li>
  <li><strong>Super</strong> - The resource is owned by a superuser of the current user</li>
  <li><strong>Sub</strong> - The resource is owned by a subuser of the current user</li>
  <li><strong>Semi</strong> - The resource is owned by a semiuser of the current user</li>
</ul>

<h5>2. Check Resource's Access Policy</h5>
<p>The user relationship is compared to the subject's <em>access policy</em>. A policy is simply an array of allowable relationships that can access the resource. This can be specified in the subject's meta:</p>
<pre>{"access": ["private", "sub"]}
  // Both the owner and sub-users of the owner can access these resources</pre>
<p>Resty will deal with attempted access to inaccessible resources depending on the action:</p>
<ul>
  <li><strong><code>GET (BROWSE)</code></strong> - Resource is simply omitted</li>
  <li><strong><code>GET, POST, PUT, DELETE</code></strong> - 404 Not Found Error</li>
</ul>

<h5>3. Check Resource's Affect Policy</h5>
<p>If the action is a <code>PUT</code>, <code>POST</code>/<code>PUT</code>, or <code>DELETE</code> request, the relationship is compared to the subject's <em>affect policy</em>. This can be specified in the subject's meta:</p>
<pre>{"affect": ["private", "semi"]}
  // Both the owner and semi-users of the owner can affect this resource</pre>
  
<h5>4. Check Field's Get/Set Policy</h5>
<p>If the action is a <code>GET</code> or <code>POST</code>/<code>PUT</code>, the <em>get policy</em> or <em>set policy</em> is checked respectively to determine if each field can be get or set. This can be specified in the field's meta:</p>
<pre>{"get": ["private", "super"], "set": ["private"]}
  // Both the owner and super-users of the owner can get this field
  // Only the owner can set this field</pre>
<p>Where a user isn't permitted to get or set a field, that field is skipped. All other fields will be processed independantly.</p>
  
<h5>5. Check Field's Referenced Subject Access Policy</h5>
<p>If the action is a <code>PUT</code>/<code>POST</code>, and a field is being set to a reference, the reference resource is considered to be being affected. Therefore, the referenced subject of resource's access policy is compared to the relationship.</p>
<p>Where the user isn't permitted to set a field to a referenced resource, that field is skipped. All other fields will be process independantly.</p>

<h5>6. Check Field's Reference Policy</h5>
<p>If the action is a <code>PUT</code>/<code>POST</code>, and a field is being set to a reference, the reference resource is considered to be being affected. Therefore, the referenced resource's reference policy is compared to the relationship.</p>
<p>Where the user isn't permitted to set a field to a referenced resource, that field is skipped. All other fields will be process independantly.</p>

<h3>License</h3>
<p>Copyright © 2014 - Jackson Capper<br/><a href='https://github.com/jacksoncapper' target='_blank'>https://github.com/jacksoncapper</a></p>
<p>Permission is granted to any person obtaining a copy of this software the rights to use, copy, and modify subject to that this license is included in all copies or substantial portions of the software. This software is provided without warranty of any kind. The author or copyright holder cannot be liable for any damages arising from the use of this software.</p>
