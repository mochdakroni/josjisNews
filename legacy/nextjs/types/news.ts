export interface Author {
  id: number;
  name: string;
  role: 'super_admin' | 'editor' | 'journalist' | string;
}

export interface Category {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  articles_count?: number;
}

export interface Tag {
  id: number;
  name: string;
  slug: string;
  articles_count?: number;
}

export interface Article {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  content_body?: string;
  featured_image_url: string | null;
  caption_image: string | null;
  is_headline: boolean;
  is_trending: boolean;
  views_count: number;
  recent_views_count?: number;
  reading_time_minutes?: number;
  published_at: string;
  updated_at?: string;
  author?: Author;
  category?: Category;
  tags?: Tag[];
}

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface ApiResponse<T> {
  status: 'success' | 'error';
  message: string;
  data: T;
  meta?: PaginationMeta & Record<string, unknown>;
}
