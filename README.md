<h1>Resty 1.0.0</h1>
<p>Publish a complete REST API from any MySQL database with authentication, security, and virtual tables built-in.</p>

<h3>Security Model</h3>
<p>Resty uses the concept of <em>ownership</em> to deny/accept read/write actions on resources and properties. Each action request goes through the following steps:</p>

<h5>1. Ownership Relationship</h5>
<p>Every resource can be <em>owned</em> by a user. Ownership is designated by a foreign key reference in that resource that links to a user directly, or to a resource that is owned by a user. A foreign key field that indicates ownership must be explicitly written in the field's meta comments:</p>
<pre>{"owner": true}</pre>
<p>Before a resource is written, read, deleted, the relationship between the current user and the resource is determined. This can result in 5 possible relationships:</p>
<ul>
  <li><strong>None</strong> - The resource has no relationship to the user
  <li><strong>Private</strong> - The resource is owned by the current user</li>
  <li><strong>Superprotected</strong> - The resource is owned by a superuser of the current user</li>
  <li><strong>Subprotected</strong> - The resource is owned by a subuser of the current user</li>
  <li><strong>Semiprotected</strong> - The resource is owned by a semiuser of the current user</li>
</ul>

<h5>2. Resource Browse Policy Check</h5>
<p>The user relationship is compared to the subject's <em>browse security policy</em>. A security policy is simply an array of allowable ownership relationships that can access the resource exists. This can be set in the subject's meta comments:</p>
<pre>{"browse": ["private", "subprotected"]}
  // Both the owner and subusers of the owner can access these resources</pre>
<p>Resty will deal with attempted access to inaccessible resources depending on the action:</p>
<ul>
  <li><strong><code>GET (BROWSE)</code></strong> - Resource is simply omitted</li>
  <li><strong><code>GET, POST, PUT, DELETE</code></strong> - 404 Not Found Error</li>
</ul>

<h5>3. Resource Update Policy Check</h5>
<p>If the action is a <code>PUT</code>, <code>POST</code>, <code>DELETE</code> request, the user relationship is compared to the subject's <em>update security policy</em>. This can be set in the subject's meta comments:</p>
<pre>{"update": ["private", "semiprotected"]}
  // Both the owner and semiusers of the owner can update this resource</pre>
  
<h5>4. Field Get/Set Policy Check</h5>
<p>If the action is a <code>GET</code> or <code>PUT</code>/<code>POST</code>, the <em>get policy</em> or <em>set policy</em> is checked respectively to determine if each field can be get or set. This can be set in the field's meta comments:</p>
<pre>{"get": ["private", "superprotected"], "set": ["private"]}
  // Both the owner and superusers of the resource can get this field
  // Only the owner can set this field</pre>
<p>Where a user isn't permitted to get or set a field, that field is skipped. All other fields will be processed independantly.</p>
  
<h5>5. Field Reference Policy Check</h5>
<p>If the action is a <code>PUT</code>/<code>POST</code>, and a field is being set to a reference, the reference resource is considered to be being updated. Therefore, the referenced resource's browse and update policies are compared the user's relationship as if that resource were being applied.</p>
<p>Where the user isn't permitted to set a field to a referenced resource, that field is skipped. All other fields will be process independantly.</p>

<h3>License</h3>
<p>Copyright © 2014 - Jackson Capper<br/><a href='https://github.com/jacksoncapper' target='_blank'>https://github.com/jacksoncapper</a></p>
<p>Permission is granted to any person obtaining a copy of this software the rights to use, copy, and modify subject to that this license is included in all copies or substantial portions of the software. This software is provided without warranty of any kind. The author or copyright holder cannot be liable for any damages arising from the use of this software.</p>
