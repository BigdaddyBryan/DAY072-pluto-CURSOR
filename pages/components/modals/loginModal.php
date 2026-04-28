<div class="createLinkContainer">
  <form id="loginForm" action="/login" method="post" class="loginForm" data-skip-refresh-after-mutation="true">
    <div class="linkInputContainer">
      <input type="email" name="email" id="email" class="linkInput">
      <label for="email" class="linkLabel"><?= htmlspecialchars(uiText('modals.login.email', 'Email'), ENT_QUOTES, 'UTF-8') ?></label>
    </div>
    <div class="linkInputContainer">
      <input type="password" name="password" id="password" class="linkInput">
      <label for="password" class="linkLabel"><?= htmlspecialchars(uiText('modals.login.password', 'Password'), ENT_QUOTES, 'UTF-8') ?></label>
    </div>
    <div class="loginButtonContainer">
      <button type="submit" class="submitButton"><?= htmlspecialchars(uiText('modals.login.submit', 'Login'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </form>
</div>