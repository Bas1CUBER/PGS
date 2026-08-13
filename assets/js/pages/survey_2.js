document.querySelectorAll(".done-checkbox").forEach(cb=>{
      cb.addEventListener("change", async ()=>{
        const id = cb.getAttribute("data-id");
        const action = cb.checked ? "mark_done" : "unmark_done";
        const fd = new FormData(); fd.append("_token","' . $csrf . '"); fd.append("action", action); fd.append("id", id);
        const r = await fetch(location.href, { method:"POST", body: fd });
        let j=null; try{ j=await r.json(); }catch(e){}
        if (!(j && j.ok)) {
          alert("Failed to update");
          cb.checked = !cb.checked;
        }
      });
    });
