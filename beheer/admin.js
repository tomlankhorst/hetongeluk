let users = [];
let selectedUser;

function initAdmin(){
  spinnerLoadCard = document.getElementById('spinnerLoad');
  initPage();
  loadUserData();

  const url = new URL(location.href);
  if (url.pathname.startsWith('/beheer/gebruikers')) loadUsers();
}

async function loadUsers(){
  let data;
  let count  = 100;
  let offset = users.length;

  function showUsers(users){
    let html = '';
    for (const user of users){
      const trclass = (user.permission === TUserPermission.admin)? ' class="bgRed" ' : '';

      html += `<tr id="truser${user.id}" ${trclass}>
<td>${user.id}</td>
<td>${user.name}<br>${user.email}</td>
<td>${datetimeToAge(user.lastactive)}</td>
<td>${permissionToText(user.permission)}</td>
<td class="trButton"><span class="editDetails">⋮</span></td>
</tr>`;
    }

    if (offset === 0){
      html = `
<table id="tableUsers" class="dataTable">
  <thead>
    <tr>
      <th>ID</th>
      <th>Name</th>
      <th>Laatst actief</th>
      <th>Permissie</th>
      <th></th>
    </tr>
  </thead>  
  <tbody id="tableBody" onclick="userTableClick(event);">
    ${html}
  </tbody>
</table>
`;
    }

    document.getElementById('users').innerHTML = html;
  }

  try {
    spinnerLoadCard.style.display = 'block';
    let url        = '/beheer/ajax.php?function=loadusers&count=' + count + '&offset=' + offset;
    const response = await fetch(url, fetchOptions);
    const text     = await response.text();
    data           = JSON.parse(text);
    if (data.user) updateLoginGUI(data.user);
    if (data.error) showError(data.error);
    else {
      data.users.forEach(user => {
        user.lastactive = new Date(user.lastactive);
      });

      users = users.concat(data.users);
      showUsers(users);
    }
  } catch (error) {
    showError(error.message);
  } finally {
    if (data.error || (data.users.length < count)) spinnerLoadCard.style.display = 'none';
  }
}

function userFromID(id) {
  return users.find(user => user.id ===id);
}

function userTableClick(event){
  event.stopPropagation();
  let tr = event.target.closest('tr');
  if (tr) selectUserTableRow(tr.rowIndex);

  closeAllPopups();
  if (event.target.classList.contains('editDetails')) showUserMenu(event.target);
}

function showUserMenu(target) {
  let menu = document.getElementById('menuArticleUser');
  if (menu) menu.remove();

  let td = target.closest('td');
  td.innerHTML += `
<div id="menuArticleUser" class="buttonPopupMenu" style="display: block !important;" onclick="event.preventDefault();">
  <div onclick="adminEditUser();">Bewerken</div>
  <div onclick="adminDeleteUser()">Verwijderen</div>
</div>            
  `;
}

function selectUserTableRow(rowIndex){
  if (selectUserTableRow.selectedRowIndex && (selectUserTableRow.selectedRowIndex === rowIndex)) return;

  let table = document.getElementById('tableUsers');

  // Hide selected row
  if (selectUserTableRow.selectedRowIndex) {
    let row = table.rows[selectUserTableRow.selectedRowIndex];
    if (! row) return;
    row.classList.remove('trSelected');
    selectedUser = null;
  }

  selectUserTableRow.selectedRowIndex = rowIndex;

  if (rowIndex) {
    let row = table.rows[rowIndex];
    row.classList.add('trSelected');

    selectedUser = users[rowIndex-1];
  }
}

function adminEditUser() {
  document.getElementById('userID').value         = selectedUser.id;
  document.getElementById('userEmail').value      = selectedUser.email;
  document.getElementById('userFirstName').value  = selectedUser.firstname;
  document.getElementById('userLastName').value   = selectedUser.lastname;
  document.getElementById('userPermission').value = selectedUser.permission;

  document.getElementById('formEditUser').style.display    = 'flex';
}

async function deleteUserDirect() {
  const userID = selectedUser.id;
  const url    = '/beheer/ajax.php?function=deleteuser&id=' + userID;
  try {
    const response = await fetch(url, fetchOptions);
    const text     = await response.text();
    const data     = JSON.parse(text);
    if (data.error) showError(data.error);
    else {
      users = users.filter(user => user.id !== userID);
      document.getElementById('truser' + userID).remove();
      showMessage('Gebruiker verwijderd');
    }
  } catch (error) {
    showError(error.message);
  }
}

async function adminDeleteUser() {
  confirmMessage(`Gebruiker #${user.id} "${selectedUser.name}" en alle items die deze gebruiker heeft aangemaakt verwijderen?<br><br><b>Dit kan niet ongedaan worden!</b>`,
    function (){
      deleteUserDirect();
    },
    `Verwijder gebruiker en zijn items`, null, true
  );
}

  async function saveUser(){
  let user = {
    id:         parseInt(document.getElementById('userID').value),
    email:      document.getElementById('userEmail').value.trim(),
    firstname:  document.getElementById('userFirstName').value.trim(),
    lastname:   document.getElementById('userLastName').value.trim(),
    permission: parseInt(document.getElementById('userPermission').value),
  };

  if (! user.email)                {showError('geen email ingevuld'); return;}
  if (! validateEmail(user.email)) {showError('geen geldig email ingevuld'); return;}
  if (! user.firstname)            {showError('geen voornaam ingevuld'); return;}
  if (! user.lastname)             {showError('geen achternaam ingevuld'); return;}

  const url = '/beheer/ajax.php?function=saveuser';
  const optionsFetch = {
    method:  'POST',
    body: JSON.stringify({
      user: user,
    }),
    headers: {'Content-Type': 'application/json'},
  };
  const response = await fetch(url, optionsFetch);
  const text     = await response.text();
  const data     = JSON.parse(text);
  if (data.error) {
    showError(data.error, 10);
  } else {
    showMessage('Gebruiker opgeslagen', 1);
    window.location.reload();
  }
}

function afterLoginAction(){
  window.location.reload();
}


function downloadData() {
  async function doDownload(){
    const spinner = document.getElementById('spinnerLoad');
    spinner.style.display = 'block';
    try {
      const maxLoadCount = 1000;
      let url        = '/ajax.php?function=loadaccidents&count=' + maxLoadCount;


      const response = await fetch(url, fetchOptions);
      const text     = await response.text();
      const data     = JSON.parse(text);
      const dataExport = {accidents: data.accidents, articles: data.articles};

      download('hetongeluk.json', JSON.stringify(dataExport));
    } finally {
      spinner.style.display = 'none';
    }

  }

  confirmMessage('Laatste 1000 ongelukken exporteren?', doDownload, 'Download');

}