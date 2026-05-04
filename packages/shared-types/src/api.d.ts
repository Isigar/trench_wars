declare namespace App {
namespace Data {
export type PlayerData = {
id: string,
user_id: string,
slug: string,
display_name: string | null,
avatar_source: string,
avatar_path: string | null,
bio: Record<string, string> | null,
country_code: string | null,
};
export type PlayerPrivacyData = {
id: string,
player_id: string,
show_to: string,
show_real_name: boolean,
show_discord_tag: boolean,
show_clan_history: boolean,
show_match_history: boolean,
show_stats: boolean,
};
export type UserData = {
id: string,
discord_id: string,
username: string,
email: string | null,
avatar_url: string | null,
locale: string,
last_login_at: string | null,
left_community_at: string | null,
};
}
}
