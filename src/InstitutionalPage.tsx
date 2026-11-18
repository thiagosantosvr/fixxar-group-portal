import { useEffect, useState } from "react";
import { ArrowLeft, ArrowUpRight } from "lucide-react";

type PageData = { slug: string; title: string; content: { summary?: string; content?: string } };
export default function InstitutionalPage({ slug }: { slug: string }) {
  const [page, setPage] = useState<PageData | null>(null);
  const [state, setState] = useState<"loading" | "ready" | "missing" | "error">("loading");
  useEffect(() => {
    let active = true;
    fetch(`/api/public/page?slug=${encodeURIComponent(slug)}`, { headers: { Accept: "application/json" }, credentials: "same-origin" })
      .then(async response => {
        if (response.status === 404) { if (active) setState("missing"); return null; }
        if (!response.ok) throw new Error("Page unavailable");
        return response.json() as Promise<{ page: PageData }>;
      })
      .then(data => { if (active && data?.page) { setPage(data.page); setState("ready"); } })
      .catch(() => { if (active) setState("error"); });
    return () => { active = false; };
  }, [slug]);
  useEffect(() => {
    document.title = page ? `${page.title} — FIXXAR GROUP` : "FIXXAR GROUP";
    let description = document.querySelector<HTMLMetaElement>('meta[name="description"]');
    if (!description) { description = document.createElement("meta"); description.name = "description"; document.head.appendChild(description); }
    description.content = page?.content.summary || "Conheça o ecossistema FIXXAR GROUP.";
  }, [page]);
  return <main className="institutional-page">
    <header className="institutional-header"><a className="wordmark" href="/">FIXXAR<span>GROUP</span><i>X</i></a><a href="/" className="institutional-back"><ArrowLeft size={15}/> Página inicial</a></header>
    {state === "loading" && <section className="institutional-message"><span className="eyebrow">FIXXAR GROUP</span><h1>Carregando conteúdo.</h1></section>}
    {state === "error" && <section className="institutional-message"><span className="eyebrow">FIXXAR GROUP</span><h1>Não foi possível carregar esta página.</h1><a href="/">Voltar ao início <ArrowUpRight size={16}/></a></section>}
    {state === "missing" && <section className="institutional-message"><span className="eyebrow">FIXXAR GROUP · 404</span><h1>Esta página não está disponível.</h1><p>O endereço pode estar incorreto ou o conteúdo ainda não foi publicado.</p><a href="/">Voltar ao início <ArrowUpRight size={16}/></a></section>}
    {state === "ready" && page && <article className="institutional-content"><span className="eyebrow">FIXXAR GROUP · INSTITUCIONAL</span><h1>{page.title}</h1>{page.content.summary && <p className="institutional-summary">{page.content.summary}</p>}<div className="institutional-body">{(page.content.content || "").split(/\n{2,}/).map((paragraph,index) => paragraph.trim() ? <p key={index}>{paragraph}</p> : null)}</div><a className="text-link dark-link" href="/"><ArrowLeft size={16}/> Voltar ao início</a></article>}
    <footer className="institutional-footer">© {new Date().getFullYear()} FIXXAR GROUP · IDEIAS QUE GANHAM FORMA</footer>
  </main>;
}
