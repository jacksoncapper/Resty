<h1>Resty 1.0.1</h1>
<p>Publish a complete REST API from any MySQL database with authentication, security, and virtual tables built-in.</p>

<h3>Security Model</h3>
<p>Resty uses a built-in, customiseable methodology for denying or allowing actions on resources and their fields.</p>

<h5>Authority</h5>
<p>When a resource is accessed or affected, the user of the request is compared to the <em>authority</em> of the resource. The authority of a resource is a user designated by an <em>authority link</em>. Authority links are the series of reference fields that link a resource eventually to the user's table. An authority link could be a direct reference to a user, or it could traverse multiple tables. Multiple authority links, and therefore multiple authorities can exist for a resource.</p>
<pre><strong>Eg:</strong> Book > Library > Librarian
  // Each book has an authority field linking to a library which has an authority field linking to a
  // librarian, who therefore is the authority of the book</pre>
<p>Reference fields which denote an authority link can be specified in the field's meta:</p>
<pre>{"authority": true}</pre>

<h5>Relationship</h5>
<p>The user's relationship to the authority is expressed as an array of values, one for each authority link:</p>
<ul>
  <li><strong>Blocked</strong> - The user is blocked from the authority</li>
  <li><strong>Public</strong> - The user has no relationship to the authority</li>
  <li><strong>Private</strong> - The user is the authority</li>
  <li><strong>Super</strong> - The user is a super-user of the authority</li>
  <li><strong>Sub</strong> - The user is a sub-user of the authority</li>
  <li><strong>Semi</strong> - The user is a semi-user of the authority</li>
</ul>
<p>Relationships can be overriden by implementing the <code>relationship</code> function in the API object overriding the default relationship algorithm.</p>

<h5>Security Policies</h5>
<p>A policy is simply an array of allowable relationships that regulate a user's ability to access or affect the resource. Policies are specified in the subject's or field's meta:</p>
<pre>{"access-policy": ["private", "sub"]}
  // Both the owner and sub-users of the owner can access these resources</pre>
<p>Security policies can be overridden by implementing the <code>[policy type]-policy</code> function in the API object overriding the default policy.</p>

<h5>Security Checkpoints</h5>
<p>Each action has a series of checkpoints where the action will be allowed or denied. A checkpoint produces a relationship, and then compares it to the relevant security policy. Only one relationship needs to match any of the relationships specified in the policy to pass each security checkpoint. When the user has a blocked relationship with any authority, this will always result in the action being denied.</p>
<ul>
  <li>
    GET
    <ol>
      <li><strong>Relationship:</strong> against resource's authority</li>
      <li><strong>Access Checkpoint:</strong> against subject's access policy</li>
      <li>
        For each field:
        <ol>
          <li><strong>Get Checkpoint:</strong> against field's get policy</li>
        </ol>
      </li>
    </ol>
  </li>
  <li>
    POST/PUT
    <ol>
      <li><strong>Relationship:</strong> against resource's authority (PUT only)</li>
      <li><strong>Affect Checkpoint:</strong> against subject's affect policy (PUT only)</li>
      <li>
        For each field:
        <ol>
          <li><strong>Set Checkpoint:</strong> against field's set policy (PUT only)</li>
          <li>
            When a reference field is being set to an existing resource:
            <ol>
              <li><strong>Relationship:</strong> against reference resource's authority</li>
              <li><strong>Access Checkpoint:</strong> against reference subject's access policy</li>
              <li><strong>Reference Checkpoint:</strong> against field's reference policy</li>
            </ol>
          </li>
        </ol>
      </li>
    </ol>
  </li>
  <li>
    DELETE
    <ol>
      <li><strong>Relationship:</strong> against resource's authority</li>
      <li><strong>Access Checkpoint:</strong> against subject's access policy</li>
      <li><strong>Affect Checkpoint:</strong> against subject's affect policy</li>
    </ol>
  </li>
</ul>

<h3>License</h3>
<p>Copyright © 2014 - Jackson Capper<br/><a href='https://github.com/jacksoncapper' target='_blank'>https://github.com/jacksoncapper</a></p>
<p>Permission is granted to any person obtaining a copy of this software the rights to use, copy, and modify subject to that this license is included in all copies or substantial portions of the software. This software is provided without warranty of any kind. The author or copyright holder cannot be liable for any damages arising from the use of this software.</p>
