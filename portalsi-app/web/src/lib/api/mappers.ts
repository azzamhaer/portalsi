import type { AnnouncementPreview, PortalUser, PostPreview, StoryPreview } from '$lib/types/domain';
import type { BackendPost, CompactUser } from '$lib/schemas/post';
import type { StoryFeedResponse } from '$lib/schemas/story';
import type { SessionUser } from '$lib/schemas/user';
import { normalizeMediaUrl } from '$lib/utils/media';
import { relativeTimeId } from '$lib/utils/time';

export function mapCompactUser(user: CompactUser, mediaBaseUrl?: string): PortalUser {
	const norm = (value?: string | null) =>
		mediaBaseUrl ? (normalizeMediaUrl(value, mediaBaseUrl) ?? undefined) : (value ?? undefined);
	// Foto asli untuk pratinjau; thumbnail kecil (kalau ada) untuk ditampilkan di avatar.
	const full = norm(user.profile_picture_url);
	const thumb = norm(user.profile_picture_thumb_url) ?? full;
	return {
		id: user.user_id,
		username: user.username,
		fullName: user.full_name?.trim() || user.username,
		avatarUrl: thumb,
		avatarFullUrl: full,
		role: user.role,
		badgeVerified: user.is_verified,
		emailVerified: true,
		isPrivate: user.is_private,
		hasStory: user.has_story ?? false,
		storyViewed: user.story_viewed ?? false,
		isFollowing: user.is_following ?? false,
		isRequested: user.is_requested ?? false,
		isSelf: user.is_self ?? false,
		followsYou: user.is_followed_by ?? false
	};
}

export function mapSessionToPortalUser(user: SessionUser): PortalUser {
	return {
		id: user.id,
		username: user.username,
		fullName: user.fullName,
		avatarUrl: user.avatarUrl ?? undefined,
		role: user.role,
		badgeVerified: user.badgeVerified,
		emailVerified: user.emailVerified,
		isPrivate: user.isPrivate,
		hasStory: false,
		storyViewed: false
	};
}

function buildVideoSources(post: BackendPost, originalUrl: string, mediaBaseUrl: string) {
	const variants = post.media_variants;
	if (!variants) return undefined;
	const out: { quality: 'low' | 'medium' | 'original'; label: string; src: string }[] = [];
	const low = normalizeMediaUrl(variants.low?.url ?? undefined, mediaBaseUrl);
	const medium = normalizeMediaUrl(variants.medium?.url ?? undefined, mediaBaseUrl);
	if (low) out.push({ quality: 'low', label: 'Rendah', src: low });
	if (medium) out.push({ quality: 'medium', label: 'Sedang', src: medium });
	out.push({
		quality: 'original',
		label: 'Asli',
		src: normalizeMediaUrl(variants.original?.url ?? undefined, mediaBaseUrl) ?? originalUrl
	});
	// Hanya berguna bila ada pilihan selain asli.
	return out.length > 1 ? out : undefined;
}

export function mapPost(post: BackendPost, mediaBaseUrl: string): PostPreview {
	const mediaUrl = normalizeMediaUrl(post.media_url, mediaBaseUrl) ?? '/assets/logo.png';
	const cleanPath = mediaUrl.split('?')[0].toLowerCase();
	const hasImageExtension = /\.(?:avif|gif|jpe?g|png|svg|webp)$/.test(cleanPath);
	const hasVideoExtension = /\.(?:3gp|m4v|mkv|mov|mp4|webm|avi)$/.test(cleanPath);
	const gallery = (post.media_urls ?? [])
		.map((url) => normalizeMediaUrl(url, mediaBaseUrl))
		.filter((url): url is string => Boolean(url));
	const media = gallery.length > 0 ? gallery : [mediaUrl];
	return {
		id: post.post_id,
		user: mapCompactUser(post.user, mediaBaseUrl),
		caption: post.caption ?? '',
		mediaUrl,
		media,
		thumbnailUrl: normalizeMediaUrl(post.thumbnail_url, mediaBaseUrl) ?? undefined,
		isVideo: hasImageExtension ? false : hasVideoExtension ? true : post.is_video,
		videoMuted: post.video_muted,
		videoSources: buildVideoSources(post, mediaUrl, mediaBaseUrl),
		coAuthors: (post.co_authors ?? []).map((u) => ({
			id: u.user_id,
			username: u.username,
			fullName: u.full_name?.trim() || u.username,
			avatarUrl:
				normalizeMediaUrl(u.profile_picture_thumb_url ?? u.profile_picture_url, mediaBaseUrl) ??
				undefined,
			verified: u.is_verified
		})),
		viewerCollabStatus: post.viewer_collab_status ?? null,
		mediaAlt: post.caption?.trim() || `Postingan oleh ${post.user.username}`,
		location: post.location ?? undefined,
		createdLabel: relativeTimeId(post.created_at),
		likesCount: post.likes_count,
		commentsCount: post.comments_count,
		isLiked: post.is_liked,
		isBookmarked: post.is_bookmarked,
		isPinned: post.is_pinned ?? false,
		isDraft: post.is_draft ?? false,
		music: post.music_track_name
			? {
					title: post.music_track_name,
					artist: post.music_artist_name ?? 'Artis tidak diketahui',
					previewUrl:
						normalizeMediaUrl(post.music_preview_url, mediaBaseUrl) ??
						post.music_preview_url ??
						undefined,
					startSeconds: (post.music_start_position_ms ?? 0) / 1000,
					durationSeconds: (post.music_clip_duration_ms ?? 15_000) / 1000
				}
			: undefined
	};
}

export function mapStoryGroups(
	response: StoryFeedResponse,
	currentUser: SessionUser,
	mediaBaseUrl: string
): StoryPreview[] {
	const ownGroup = response.stories.find((group) => group.user_id === currentUser.id);
	const ownUser = mapSessionToPortalUser(currentUser);
	ownUser.hasStory = Boolean(ownGroup);
	ownUser.storyViewed = ownGroup?.is_viewed ?? false;
	const own: StoryPreview = {
		id: ownGroup?.stories[0]?.story_id ?? 0,
		user: ownUser,
		isOwn: true
	};
	const groups = response.stories
		.filter((group) => group.user_id !== currentUser.id)
		.map<StoryPreview>((group) => ({
			id: group.stories[0].story_id,
			recommended: group.is_recommended,
			user: {
				id: group.user_id,
				username: group.username,
				fullName: group.full_name?.trim() || group.username,
				avatarUrl:
					normalizeMediaUrl(group.profile_picture_thumb_url ?? group.profile_picture_url, mediaBaseUrl) ??
					undefined,
				role: group.role,
				badgeVerified: group.is_verified,
				emailVerified: true,
				isPrivate: false,
				hasStory: true,
				storyViewed: group.is_viewed
			}
		}));
	return [own, ...groups];
}

export function mapAnnouncement(announcement: {
	id: number;
	title?: string | null;
	content?: string | null;
	created_at: string;
	pinned: boolean;
}): AnnouncementPreview {
	return {
		id: announcement.id,
		title: announcement.title?.trim() || 'Pengumuman Portal SI',
		content: announcement.content?.trim() || 'Tidak ada rincian tambahan.',
		createdLabel: relativeTimeId(announcement.created_at),
		pinned: announcement.pinned
	};
}
