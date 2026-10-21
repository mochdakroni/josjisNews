import { cache } from 'react';
import { ApiResponse, Article, Category, Tag } from '@/types/news';

export const SITE_URL =
  process.env.NEXT_PUBLIC_SITE_URL?.replace(/\/$/, '') ||
  'https://josjisnews.com';

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL?.replace(/\/$/, '') ||
  'http://localhost:8080/api/v1';

interface GetArticlesParams {
  page?: number;
  per_page?: number;
  category?: string;
  tag?: string;
  search?: string;
  sort?: 'latest' | 'popular' | 'oldest';
}

async function fetchJson<T>(
  endpoint: string,
  revalidateSeconds: number = 60
): Promise<ApiResponse<T>> {
  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    headers: {
      Accept: 'application/json',
    },
    next: { revalidate: revalidateSeconds },
  });

  if (!response.ok) {
    throw new Error(`API error (${response.status}): ${endpoint}`);
  }

  return response.json();
}

/**
 * GET /api/v1/articles/headline
 */
export async function getHeadlineArticles(): Promise<Article[]> {
  try {
    const res = await fetchJson<Article[]>('/articles/headline', 60);
    return res.data ?? [];
  } catch {
    return [];
  }
}

/**
 * GET /api/v1/articles/trending
 */
export async function getTrendingArticles(
  hours: 24 | 48 = 48,
  limit: number = 5
): Promise<Article[]> {
  try {
    const res = await fetchJson<Article[]>(
      `/articles/trending?hours=${hours}&limit=${limit}`,
      120
    );
    return res.data ?? [];
  } catch {
    return [];
  }
}

/**
 * GET /api/v1/articles
 */
export async function getArticles(
  params: GetArticlesParams = {}
): Promise<ApiResponse<Article[]>> {
  const searchParams = new URLSearchParams();

  if (params.page) searchParams.set('page', String(params.page));
  if (params.per_page) searchParams.set('per_page', String(params.per_page));
  if (params.category) searchParams.set('category', params.category);
  if (params.tag) searchParams.set('tag', params.tag);
  if (params.search) searchParams.set('search', params.search);
  if (params.sort) searchParams.set('sort', params.sort);

  const queryString = searchParams.toString();
  const endpoint = `/articles${queryString ? `?${queryString}` : ''}`;

  try {
    return await fetchJson<Article[]>(endpoint, 60);
  } catch {
    const perPage = params.per_page ?? 10;
    const page = params.page ?? 1;
    return {
      status: 'success',
      message: 'Empty result',
      data: [],
      meta: {
        current_page: page,
        per_page: perPage,
        total: 0,
        last_page: 1,
      },
    };
  }
}

/**
 * GET /api/v1/categories
 */
export async function getActiveCategories(): Promise<Category[]> {
  try {
    const res = await fetchJson<Category[]>('/categories', 600);
    return res.data ?? [];
  } catch {
    return [];
  }
}

/**
 * GET /api/v1/tags/popular
 */
export async function getPopularTags(limit: number = 10): Promise<Tag[]> {
  try {
    const res = await fetchJson<Tag[]>(`/tags/popular?limit=${limit}`, 600);
    return res.data ?? [];
  } catch {
    return [];
  }
}

/**
 * GET /api/v1/articles/{slug}
 */
export const getArticleBySlug = cache(
  async (slug: string): Promise<Article | null> => {
    try {
      const res = await fetchJson<Article>(
        `/articles/${encodeURIComponent(slug)}`,
        60
      );
      return res.data ?? null;
    } catch {
      return null;
    }
  }
);
