$(document).ready(function() {
  let i = 0;
  let remote_select = $("#remote_select")[0];
  remote_list.forEach(e => {
    console.log(e);
    remote_select.innerHTML += `<option value=${i}>${e}</option>`;
    i++;
  });

});
