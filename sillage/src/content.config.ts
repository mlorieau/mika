import { defineCollection, z } from 'astro:content';
import { glob } from 'astro/loaders';

const objets = defineCollection({
  loader: glob({ pattern: '**/*.md', base: './src/content/objets' }),
  schema: z.object({
    // Identité de la pièce
    titre: z.string(),
    // Courte phrase d'évocation affichée sous le titre (liste, carte)
    accroche: z.string(),
    // Statut de la pièce : disponible en vente, réservée, ou archivée (vendue)
    statut: z.enum(['disponible', 'reservee', 'archivee']),

    // Le récit éditorial (ce que l'objet évoque) s'écrit dans le corps
    // du fichier Markdown, sous le bloc d'informations ci-dessous.

    // Informations factuelles, vérifiables, honnêtes
    epoque: z.string().optional(),
    origine: z.string().optional(),
    materiaux: z.string().optional(),
    dimensions: z.string().optional(),
    etat: z.string().optional(),

    // Prix affiché (V1 : sur demande possible si prix absent)
    prix: z.number().optional(),
    devise: z.string().default('EUR'),

    // Images : la première fait rêver, les suivantes montrent la réalité
    images: z
      .array(
        z.object({
          src: z.string(),
          alt: z.string(),
        }),
      )
      .min(1),

    // Mise en avant sur la page d'accueil
    misEnAvant: z.boolean().default(false),

    // Ordre d'affichage manuel (plus petit = affiché en premier)
    ordre: z.number().default(100),

    // Date d'entrée dans la sélection (utile pour tri / archives)
    dateEntree: z.coerce.date(),
    // Date de vente, uniquement pour les pièces archivées
    dateVente: z.coerce.date().optional(),

    // Préparation Stripe (V2) — laisser vide en V1
    stripePriceId: z.string().optional(),
  }),
});

export const collections = { objets };
