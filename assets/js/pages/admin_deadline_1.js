async function setDeadline(role) {
      const fd = new FormData();
      fd.append("action","set");
      fd.append("_token","' . $csrf . '");
      fd.append("role", role);
      fd.append("days", document.getElementById("days_"+role).value || "0");
      fd.append("hours", document.getElementById("hours_"+role).value || "0");
      fd.append("minutes", document.getElementById("mins_"+role).value || "0");
      fd.append("message", document.getElementById("msg_"+role).value || "");
      const r = await fetch(location.href, { method:"POST", body:fd });
      const j = await r.json().catch(()=>null);
      if (!j || !j.ok) { alert((j && j.msg) || "Failed to set deadline"); return; }
      location.reload();
    }
    async function resetDeadline(role) {
      const fd = new FormData();
      fd.append("action","reset");
      fd.append("_token","' . $csrf . '");
      fd.append("role", role);
      const r = await fetch(location.href, { method:"POST", body:fd });
      const j = await r.json().catch(()=>null);
      if (!j || !j.ok) { alert((j && j.msg) || "Failed to reset"); return; }
      location.reload();
    }
