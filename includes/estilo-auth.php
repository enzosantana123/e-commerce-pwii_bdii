<style>
:root{--bg:#011815;--painel:#293740;--painel-2:#2d334a;--verde:#33ebaa;--verde-claro:#3bffb9;--texto:#F8F0E3;--muted:#D9C5C5;--borda:rgba(55,212,173,.2)}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;background:var(--bg);font-family:Inter,Arial,sans-serif;color:var(--texto);display:flex;align-items:center;justify-content:center;padding:28px}
a{color:var(--verde-claro);text-decoration:none}
a:hover{color:var(--verde)}
.auth-wrap{width:100%;max-width:460px}
.voltar{display:inline-block;margin-bottom:20px;color:var(--muted)}
.auth-card{background:linear-gradient(135deg,var(--painel-2),var(--painel));border:1px solid var(--borda);border-radius:20px;padding:34px;box-shadow:0 18px 50px rgba(0,0,0,.4)}
.logo{display:block;width:150px;max-height:65px;object-fit:contain;margin:0 auto 12px}
.auth-card h1{font-family:Orbitron,Arial,sans-serif;text-align:center;font-size:25px;margin:8px 0;color:var(--texto)}
.subtitulo{text-align:center;color:var(--muted);font-size:14px;margin:0 0 25px}
.campo{margin-bottom:16px}
.campo label{display:block;font-size:13px;margin-bottom:7px;color:var(--texto)}
.campo input{width:100%;padding:13px 14px;border-radius:9px;border:1px solid rgba(55,212,173,.35);background:#011815;color:white;font:inherit;outline:none}
.campo input:focus{border-color:var(--verde);box-shadow:0 0 0 3px rgba(51,235,170,.13)}
.botao{width:100%;padding:14px;border:0;border-radius:9px;background:var(--verde);color:#102b25;font-weight:800;font-size:15px;cursor:pointer;margin-top:5px}
.botao:hover{background:var(--verde-claro)}
.alternativa{text-align:center;color:var(--muted);font-size:14px;margin:22px 0 0}
.mensagem{padding:12px;border-radius:8px;margin-bottom:17px;font-size:14px;background:#713b35;color:#ffe5df}
.mensagem.sucesso{background:#174d3c;color:#d7ffed}
.grade{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}
@media(max-width:480px){.auth-card{padding:25px 20px}.grade{display:block}}
</style>
