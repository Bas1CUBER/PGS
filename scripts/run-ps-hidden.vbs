' Zero-flash launcher for scheduled PowerShell scripts.
' wscript.exe is a GUI-subsystem host, so no console is ever allocated -
' unlike launching powershell.exe directly from Task Scheduler, which
' briefly flashes a window before -WindowStyle Hidden applies.
' Usage: wscript.exe run-ps-hidden.vbs "C:\path\to\script.ps1"
Set shell = CreateObject("WScript.Shell")
If WScript.Arguments.Count < 1 Then WScript.Quit 1
cmd = "powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File """ & WScript.Arguments(0) & """"
shell.Run cmd, 0, False
