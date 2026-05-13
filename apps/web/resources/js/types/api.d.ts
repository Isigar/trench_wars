declare namespace App {
namespace Data {
export type ClanApplicationData = {
id: string,
clan_id: string,
applicant_user_id: string,
status: string,
message: string | null,
decided_at: string | null,
decided_by: string | null,
applicant_username: string | null,
};
export type ClanData = {
id: string,
slug: string,
tag: string,
name: string,
description: Record<string, string> | null,
country_code: string | null,
status: string,
discord_role_id: string | null,
tags: App.Data.ClanTagData[],
active_member_count: number,
};
export type ClanInviteData = {
id: string,
clan_id: string,
invited_user_id: string,
inviting_user_id: string,
status: string,
message: string | null,
decided_at: string | null,
expires_at: string | null,
};
export type ClanMembershipData = {
id: string,
clan_id: string,
user_id: string,
role: string,
joined_at: string | null,
left_at: string | null,
invited_by: string | null,
username: string | null,
avatar_url: string | null,
player_slug: string | null,
};
export type ClanTagData = {
id: string,
slug: string,
label: Record<string, string> | null,
color: string | null,
};
export type GameData = {
id: string,
key: string,
name: Record<string, string> | null,
is_active: boolean,
roles: App.Data.GameRoleData[],
match_types: App.Data.GameMatchTypeData[],
};
export type GameMatchTypeData = {
id: string,
game_id: string,
key: string,
name: Record<string, string> | null,
description: Record<string, string> | null,
is_active: boolean,
role_limits: App.Data.GameMatchTypeRoleLimitData[],
};
export type GameMatchTypeRoleLimitData = {
id: string,
game_match_type_id: string,
game_role_id: string,
capacity: number,
sort_order: number,
};
export type GameRoleData = {
id: string,
game_id: string,
key: string,
display_name: Record<string, string> | null,
sort_order: number,
is_active: boolean,
};
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
export type PublicPlayerData = {
id: string,
slug: string,
displayName: string,
avatarUrl: string,
isOwnProfile: boolean,
countryCode: string | null,
discordTag: undefined | string | null,
bio: undefined | Record<string, string> | null,
currentClan: undefined | App.Data.ClanMembershipData | null,
clanHistory: undefined | Record<string, any>[] | null,
matchHistory: undefined | any[] | null,
stats: undefined | any[] | null,
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
