export type Universe = {
  id: string;
  name: string;
  eyebrow: string;
  description: string;
  detail: string;
  category: "Estratégia" | "Imagem" | "Editorial" | "Infantil" | "Personalização" | "Tecnologia";
  url?: string;
  status: "active" | "in-development" | "archived";
  sortOrder?: number;
  logoUrl?: string;
  logoBackground?: "transparent" | "black" | "white" | "brand";
  coverImageUrl?: string;
  contactEmail?: string;
  contactPhone?: string;
  whatsappUrl?: string;
  instagramUrl?: string;
  facebookUrl?: string;
  youtubeUrl?: string;
  linkedinUrl?: string;
  address?: string;
};

export const universes: Universe[] = [
  { id: "agencia", name: "Fixxar Agência", eyebrow: "Estratégia & comunicação", description: "Marcas, experiências e presença digital construídas com intenção.", detail: "Estratégia, comunicação, branding e soluções digitais.", category: "Estratégia", url: "https://agencia.fixxarestudios.com.br", status: "active" },
  { id: "fotografia", name: "Fixxar Fotografia", eyebrow: "Imagem & experiência", description: "Fotografia profissional, histórias e experiências que permanecem.", detail: "Fotografia profissional, álbuns e formação.", category: "Imagem", url: "https://thiago.fixxarestudios.com.br", status: "active" },
  { id: "editora", name: "Entre o Céu e Papel", eyebrow: "Projetos editoriais", description: "Ideias transformadas em livros, conhecimento e memória.", detail: "Editora independente, projetos editoriais e consultoria acadêmica.", category: "Editorial", status: "in-development" },
  { id: "tio-moises", name: "Tio Moisés", eyebrow: "Universo infantil", description: "Histórias, conteúdos educativos e experiências para crianças.", detail: "Conteúdo educativo, histórias, personagens e experiências.", category: "Infantil", status: "in-development" },
  { id: "brindes", name: "Fixxar Brindes", eyebrow: "Personalização", description: "Ideias que ganham forma em produtos e presentes personalizados.", detail: "Brindes e soluções de personalização.", category: "Personalização", status: "in-development" },
  { id: "yad-sistemas-integrados", name: "YAD Sistemas Integrados", eyebrow: "Tecnologia & integração", description: "Soluções integradas para conectar processos, sistemas e operações.", detail: "Unidade de tecnologia dedicada a sistemas integrados e soluções digitais. Informações comerciais e endereço oficial serão definidos na etapa de lançamento.", category: "Tecnologia", status: "in-development" },
  { id: "st-seg", name: "ST SEG", eyebrow: "Segurança & treinamentos", description: "Soluções em segurança do trabalho, capacitação e treinamentos normativos.", detail: "Consultoria, segurança do trabalho e turmas personalizadas para treinamentos das normas.", category: "Estratégia", status: "active", sortOrder: 7 }
];

export const pillars = [
  { number: "01", title: "Criatividade", text: "Imaginar novas possibilidades e enxergar caminhos onde outros veem limites." },
  { number: "02", title: "Comunicação", text: "Conectar marcas e pessoas por meio de mensagens que fazem sentido." },
  { number: "03", title: "Conhecimento", text: "Transformar experiência, pesquisa e repertório em valor compartilhado." },
  { number: "04", title: "Inovação", text: "Construir soluções relevantes, combinando visão, tecnologia e atitude." }
];