document.getElementById("addForm")?.addEventListener("submit", async (e)=>{
      e.preventDefault();
      const fd = new FormData(e.target);
      fd.append("_token","' . $csrf . '");
      fd.append("action","create");
      const r = await fetch(location.href, { method:"POST", body: fd });
      let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.ok) { const m = bootstrap.Modal.getInstance(document.getElementById("addModal")); m?.hide(); location.reload(); }
      else { alert(j && j.msg ? j.msg : "Failed"); }
    });
    document.querySelectorAll(".btn-edit").forEach(btn=>{
      btn.addEventListener("click", ()=>{
        document.getElementById("editId").value = btn.getAttribute("data-id");
        document.getElementById("editTitle").value = btn.getAttribute("data-title");
        document.getElementById("editUrl").value = btn.getAttribute("data-url");
        new bootstrap.Modal(document.getElementById("editModal")).show();
      });
    });
    document.getElementById("editForm")?.addEventListener("submit", async (e)=>{
      e.preventDefault();
      const fd = new FormData(e.target);
      fd.append("_token","' . $csrf . '");
      fd.append("action","edit");
      const r = await fetch(location.href, { method:"POST", body: fd });
      let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.ok) { const m = bootstrap.Modal.getInstance(document.getElementById("editModal")); m?.hide(); location.reload(); }
      else { alert(j && j.msg ? j.msg : "Failed"); }
    });
    document.querySelectorAll(".btn-archive").forEach(btn=>{
      btn.addEventListener("click", async ()=>{
        if (!confirm("Archive this survey link?")) return;
        const fd = new FormData(); fd.append("_token","' . $csrf . '"); fd.append("action","archive"); fd.append("id", btn.getAttribute("data-id"));
        const r = await fetch(location.href, { method:"POST", body: fd });
        let j=null; try{ j=await r.json(); }catch(e){}
        if (j && j.ok) location.reload(); else alert("Failed");
      });
    });
    document.querySelectorAll(".btn-delete").forEach(btn=>{
      btn.addEventListener("click", async ()=>{
        if (!confirm("Permanently delete this archived link?")) return;
        const fd = new FormData(); fd.append("_token","' . $csrf . '"); fd.append("action","delete_archived"); fd.append("id", btn.getAttribute("data-id"));
        const r = await fetch(location.href, { method:"POST", body: fd });
        let j=null; try{ j=await r.json(); }catch(e){}
        if (j && j.ok) location.reload(); else alert("Failed");
      });
    });
    document.querySelectorAll(".btn-done-list").forEach(btn=>{
      btn.addEventListener("click", async ()=>{
        const id = btn.getAttribute("data-id");
        const fd = new FormData(); fd.append("_token","' . $csrf . '"); fd.append("action","get_done_list"); fd.append("id", id);
        const r = await fetch(location.href, { method:"POST", body: fd });
        let j=null; try{ j=await r.json(); }catch(e){}
        const ul = document.getElementById("doneList");
        ul.innerHTML = "";
        if (j && j.ok && Array.isArray(j.users)) {
          if (j.users.length === 0) {
            ul.innerHTML = "<li class=\"list-group-item text-muted\">No users yet.</li>";
          } else {
            j.users.forEach(u=>{
              const li = document.createElement("li");
              li.className = "list-group-item";
              li.textContent = u.email;
              ul.appendChild(li);
            });
          }
          new bootstrap.Modal(document.getElementById("doneListModal")).show();
        } else {
          alert("Failed to load list");
        }
      });
    });
