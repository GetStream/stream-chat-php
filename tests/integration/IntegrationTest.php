<?php

namespace GetStream\Integration;

use GetStream\StreamChat\Client;
use GetStream\StreamChat\StreamException;
use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    /**
     * @var Client
     */
    protected $client;

    protected function setUp():void
    {
        $this->client = new Client(getenv('STREAM_KEY'), getenv('STREAM_SECRET'));
        $this->client->timeout = 10000;
    }

    private function generateGuid()
    {
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    }

    public function testHttpClientSet()
    {
        $client = new Client(getenv('STREAM_KEY'), getenv('STREAM_SECRET'));

        $client->setHttpClient(new \GuzzleHttp\Client(['base_uri' => 'https://getstream.io']));
        try {
            $response = $this->client->getAppSettings();
            $this->fail("Expected to throw exception");
        } catch (\Exception $e) {
        }

        $client->setHttpClient(new \GuzzleHttp\Client(['base_uri' => 'https://chat.stream-io-api.com']));
        $response = $this->client->getAppSettings();
        $this->assertTrue(array_key_exists("app", (array)$response));
    }

    public function testStreamResponse()
    {
        $response = $this->client->getAppSettings();
        $rateLimits = $response->getRateLimits();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertGreaterThan(0, $rateLimits->getLimit());
        $this->assertGreaterThan(0, $rateLimits->getRemaining());
        $this->assertNotNull($rateLimits->getReset());

        $serialized = json_encode($response);
        $this->assertFalse(str_contains($serialized, "rate"));
        $this->assertTrue(str_starts_with($serialized, '{"app"'));
    }

    public function testAuth()
    {
        $this->expectException(\GetStream\StreamChat\StreamException::class);
        $this->client = new Client("bad", "guy");
        $this->client->getAppSettings();
    }

    public function testChannelTypes()
    {
        $response = $this->client->getChannelType("team");
        $this->assertTrue(array_key_exists("permissions", (array)$response));
    }

    public function testListChannelTypes()
    {
        $response = $this->client->listChannelTypes();
        $this->assertTrue(array_key_exists("channel_types", (array)$response));
    }

    private function getUser()
    {
        // this creates a user on the server
        $user = ["id" => $this->generateGuid()];
        $response = $this->client->upsertUser($user);
        $this->assertTrue(array_key_exists("users", (array)$response));
        $this->assertTrue(array_key_exists($user["id"], $response["users"]));
        return $user;
    }

    public function testStreamException()
    {
        try {
            $this->client->muteUser("invalid_user_id", "invalid_user_id");
            $this->fail("An exception must be thrown.");
        } catch (StreamException $e) {
            $this->assertGreaterThan(0, $e->getRateLimitLimit());
        }
    }

    public function testMuteUser()
    {
        $user1 = $this->getUser();
        $user2 = $this->getUser();
        $response = $this->client->muteUser($user1["id"], $user2["id"]);
        $this->assertTrue(array_key_exists("mute", (array)$response));
        $this->assertSame($response["mute"]["target"]["id"], $user1["id"]);
    }

    public function testGetAppSettings()
    {
        $response = $this->client->getAppSettings();
        $this->assertTrue(array_key_exists("app", (array)$response));
    }

    public function testUpdateAppSettings()
    {
        $response = $this->client->getAppSettings();
        $settings = $response['app'];
        $response = $this->client->updateAppSettings($settings);
        $this->assertTrue(array_key_exists("duration", (array)$response));
    }

    public function testCheckPush()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();
        $response = $channel->sendMessage(["text" => "How many syllables are there in xyz"], $user["id"]);

        $pushResponse = $this->client->checkPush(["message_id" => $response["message"]["id"], "skip_devices" => true, "user_id" => $user["id"]]);

        $this->assertTrue(array_key_exists("rendered_message", (array)$pushResponse));
    }

    public function testCheckSqs()
    {
        $response = $this->client->checkSqs([
            "sqs_url" => "https://foo.com/bar",
            "sqs_key" => "key",
            "sqs_secret" => "secret"]);

        $this->assertTrue(array_key_exists("status", (array)$response));
    }

    public function testGuestUser()
    {
        try {
            $response = $this->client->setGuestUser(["user" => ["id" => $this->generateGuid()]]);
        } catch (\Exception $e) {
            // Guest user isn't allowed on all applications
            return;
        }

        $this->assertTrue(array_key_exists("access_token", (array)$response));
    }

    public function testUpsertUser()
    {
        $user = ["id" => $this->generateGuid()];
        $response = $this->client->upsertUser($user);
        $this->assertTrue(array_key_exists("users", (array)$response));
        $this->assertTrue(array_key_exists($user["id"], $response["users"]));
    }

    public function testUpsertUsers()
    {
        $user = ["id" => $this->generateGuid()];
        $response = $this->client->upsertUsers([$user]);
        $this->assertTrue(array_key_exists("users", (array)$response));
        $this->assertTrue(array_key_exists($user["id"], $response["users"]));
    }

    public function testDeleteUser()
    {
        $user = $this->getUser();
        $response = $this->client->deleteUser($user["id"]);
        $this->assertTrue(array_key_exists("user", (array)$response));
        $this->assertSame($user["id"], $response["user"]["id"]);
    }

    public function testDeleteUsers()
    {
        $user = $this->getUser();
        $response = $this->client->deleteUsers([$user["id"]], ["user" => "hard"]);
        $this->assertTrue(array_key_exists("task_id", (array)$response));
        $taskId = $response["task_id"];
        for ($i=0;$i<30;$i++) {
            $response = $this->client->getTask($taskId);
            if ($response["status"] == "completed") {
                $this->assertSame($response["result"][$user["id"]]["status"], "ok");
                return;
            }
            usleep(300000);
        }
        $this->assertSame($response["status"], "completed");
    }

    public function testDeleteChannels()
    {
        $user = ["id" => $this->generateGuid()];
        $response = $this->client->upsertUser($user);

        $c1 = $this->getChannel();
        $c2 = $this->getChannel();

        $response = $this->client->deleteChannels([$c1->getCID(), $c2->getCID()], ["hard_delete" => true]);
        $this->assertTrue(array_key_exists("task_id", (array)$response));

        $taskId = $response["task_id"];
        for ($i=0;$i<30;$i++) {
            $response = $this->client->getTask($taskId);
            if ($response["status"] == "completed") {
                $this->assertSame($response["result"][$c1->getCID()]["status"], "ok");
                $this->assertSame($response["result"][$c2->getCID()]["status"], "ok");
                return;
            }
            usleep(300000);
        }
        $this->assertSame($response["status"], "completed");
    }

    public function testDeactivateUser()
    {
        $user = $this->getUser();
        $response = $this->client->deactivateUser($user["id"]);
        $this->assertTrue(array_key_exists("user", (array)$response));
        $this->assertSame($user["id"], $response["user"]["id"]);
    }

    public function testReactivateUser()
    {
        $user = $this->getUser();
        $response = $this->client->deactivateUser($user["id"]);
        $this->assertTrue(array_key_exists("user", (array)$response));
        $response = $this->client->reactivateUser($user["id"]);
        $this->assertTrue(array_key_exists("user", (array)$response));
        $this->assertSame($user["id"], $response["user"]["id"]);
    }

    public function testReactivateUserError()
    {
        $user = $this->getUser();
        $this->expectException(\GetStream\StreamChat\StreamException::class);
        $response = $this->client->reactivateUser($user["id"]);
    }

    public function createFellowship()
    {
        $members = [
            ["id" => "frodo-baggins", "name" => "Frodo Baggins", "race" => "Hobbit", "age" => 50],
            ["id" => "sam-gamgee", "name" => "Samwise Gamgee", "race" => "Hobbit", "age" => 38],
            ["id" => "gandalf", "name" => "Gandalf the Grey", "race" => "Istari"],
            ["id" => "legolas", "name" => "Legolas", "race" => "Elf", "age" => 500],
            ["id" => "gimli", "name" => "Gimli", "race" => "Dwarf", "age" => 139],
            ["id" => "aragorn", "name" => "Aragorn", "race" => "Man", "age" => 87],
            ["id" => "boromir", "name" => "Boromir", "race" => "Man", "age" => 40],
            [
                "id" => "meriadoc-brandybuck",
                "name" => "Meriadoc Brandybuck",
                "race" => "Hobbit",
                "age" => 36,
            ],
            ["id" => "peregrin-took", "name" => "Peregrin Took", "race" => "Hobbit", "age" => 28],
        ];
        $this->client->upsertUsers($members);
        $user_ids = [];
        foreach ($members as $user) {
            $user_ids[] = $user['id'];
        }
        $channel = $this->client->Channel(
            "team",
            "fellowship-of-the-ring",
            ["members" => $user_ids]
        );

        $channel->create("gandalf");
    }

    public function testExportUser()
    {
        $this->createFellowship();
        $response = $this->client->exportUser("gandalf");
        $this->assertTrue(array_key_exists("user", (array)$response));
        $this->assertSame("Gandalf the Grey", $response["user"]["name"]);
    }

    public function testShadowban()
    {
        $user1 = $this->getUser();
        $user2 = $this->getUser();
        $channel = $this->getChannel();

        $response = $channel->sendMessage(["text" => "hello world"], $user1["id"]);
        $this->assertFalse($response["message"]["shadowed"]);
        $response = $this->client->getMessage($response["message"]["id"]);
        $this->assertFalse($response["message"]["shadowed"]);

        $this->client->shadowBan($user1["id"], ["user_id" => $user2["id"]]);

        $response = $channel->sendMessage(["text" => "hello world"], $user1["id"]);
        $this->assertFalse($response["message"]["shadowed"]);
        $response = $this->client->getMessage($response["message"]["id"]);
        $this->assertTrue($response["message"]["shadowed"]);

        $this->client->removeShadowBan($user1["id"], ["user_id" => $user2["id"]]);

        $response = $channel->sendMessage(["text" => "hello world"], $user1["id"]);
        $this->assertFalse($response["message"]["shadowed"]);
        $response = $this->client->getMessage($response["message"]["id"]);
        $this->assertFalse($response["message"]["shadowed"]);
    }

    public function testPinMessage()
    {
        $user1 = $this->getUser();
        $user2 = $this->getUser();
        $channel = $this->getChannel();

        $response = $channel->sendMessage(["text" => "hello world"], $user1["id"]);
        $this->assertNull($response["message"]["pinned_at"]);
        $this->assertNull($response["message"]["pinned_by"]);

        $this->client->pinMessage($response["message"]["id"], $user2["id"]);

        $response = $this->client->getMessage($response["message"]["id"]);
        $this->assertNotNull($response["message"]["pinned_at"]);
        $this->assertEquals($user2["id"], $response["message"]["pinned_by"]["id"]);

        $this->client->unPinMessage($response["message"]["id"], $user2["id"]);

        $response = $this->client->getMessage($response["message"]["id"]);
        $this->assertNull($response["message"]["pinned_at"]);
        $this->assertNull($response["message"]["pinned_by"]);
    }

    public function testBanUser()
    {
        $user1 = $this->getUser();
        $user2 = $this->getUser();
        $response = $this->client->banUser($user1["id"], ["user_id" => $user2["id"]]);
    }

    public function testUnBanUser()
    {
        $user1 = $this->getUser();
        $user2 = $this->getUser();
        $response = $this->client->banUser($user1["id"], ["user_id" => $user2["id"]]);
        $response = $this->client->unBanUser($user1["id"], ["user_id" => $user2["id"]]);
    }

    public function testQueryBannedUsers()
    {
        $user1 = $this->getUser();
        $user2 = $this->getUser();
        $response = $this->client->banUser($user1["id"], ["user_id" => $user2["id"], "reason" => "because"]);

        $queryResp = $this->client->queryBannedUsers(["reason" => "because"], ["limit" => 1]);

        $this->assertTrue(array_key_exists("bans", (array)$queryResp));
    }

    public function testBlockListsEndToEnd()
    {
        $name = $this->generateGuid();

        $this->client->createBlocklist(["name" => $name, "words" => ["test"]]);

        $listResp = $this->client->listBlocklists();
        $this->assertTrue(array_key_exists("blocklists", (array)$listResp));

        $getResp = $this->client->getBlocklist($name);
        $this->assertTrue(array_key_exists("blocklist", (array)$getResp));

        $updateResp = $this->client->updateBlocklist($name, ["words" => ["test", "test2"]]);
        $this->assertTrue(array_key_exists("duration", (array)$updateResp));

        $deleteResp = $this->client->deleteBlocklist($name);
        $this->assertTrue(array_key_exists("duration", (array)$deleteResp));
    }

    public function testCommandsEndToEnd()
    {
        $name = $this->generateGuid();

        $this->client->createCommand(["name" => $name, "description" => "Test php end2end test"]);

        $listResp = $this->client->listCommands();
        $this->assertTrue(array_key_exists("commands", (array)$listResp));

        $getResp = $this->client->getCommand($name);
        $this->assertEquals($name, $getResp["name"]);

        $updateResp = $this->client->updateCommand($name, ["description" => "Test php end2end test 2"]);
        $this->assertTrue(array_key_exists("duration", (array)$updateResp));

        $updateResp = $this->client->deleteCommand($name);
        $this->assertTrue(array_key_exists("duration", (array)$updateResp));
    }

    public function testSendMessageAction()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();
        $msgId = $this->generateGuid();
        $channel->sendMessage(["id" => $msgId, "text" => "/giphy wave"], $user["id"]);

        $response = $this->client->sendMessageAction($msgId, $user["id"], ["image_action" => "shuffle"]);

        $this->assertTrue(array_key_exists("message", (array)$response));
    }

    public function testTranslateMessage()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();
        $msgId = $this->generateGuid();
        $channel->sendMessage(["id" => $msgId, "text" => "hello world"], $user["id"]);

        $response = $this->client->translateMessage($msgId, "hu");

        $this->assertTrue(array_key_exists("message", (array)$response));
    }

    public function testFlagUser()
    {
        $user1 = $this->getUser();
        $user2 = $this->getUser();
        $response = $this->client->flagUser($user1["id"], ["user_id" => $user2["id"]]);
    }

    public function testUnFlagUser()
    {
        $user1 = $this->getUser();
        $user2 = $this->getUser();
        $response = $this->client->flagUser($user1["id"], ["user_id" => $user2["id"]]);
        $response = $this->client->unFlagUser($user1["id"], ["user_id" => $user2["id"]]);
    }

    public function testMarkAllRead()
    {
        $user1 = $this->getUser();
        $response = $this->client->markAllRead($user1["id"]);
    }

    public function getChannel()
    {
        $channel = $this->client->Channel(
            "messaging",
            $this->generateGuid(),
            ["test" => true, "language" => "php"]
        );
        $channel->create($this->getUser()["id"]);
        return $channel;
    }

    public function testChannelWithoutData()
    {
        $channel = $this->client->Channel(
            "messaging",
            $this->generateGuid()
        );
        $channel->create($this->getUser()["id"]);
        return $channel;
    }

    public function testGetChannelWithoutData()
    {
        $channel = $this->client->getChannel(
            "messaging",
            $this->generateGuid()
        );
        $channel->create($this->getUser()["id"]);
        return $channel;
    }

    public function testUpdateMessage()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();
        $msgId = $this->generateGuid();
        $msg = ["id" => $msgId, "text" => "hello world"];
        $response = $channel->sendMessage($msg, $user["id"]);
        $this->assertSame("hello world", $response["message"]["text"]);
        $msg = [
            "id" => $msgId,
            "text" => "hello world",
            "awesome" => true,
            "user" => ["id" => $response["message"]["user"]["id"]]
        ];
        $response = $this->client->updateMessage($msg);
    }

    public function testDeleteMessage()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();
        $msgId = $this->generateGuid();
        $msg = ["id" => $msgId, "text" => "helloworld"];
        $response = $channel->sendMessage($msg, $user["id"]);
        $response = $this->client->deleteMessage($msgId);
    }

    public function testManyMessages()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();
        $msgId = $this->generateGuid();
        $msg = ["id" => $msgId, "text" => "helloworld"];
        $channel->sendMessage($msg, $user["id"]);

        $msgResponse = $channel->getManyMessages([$msgId]);

        $this->assertTrue(array_key_exists("messages", (array)$msgResponse));
    }

    public function testFlagMessage()
    {
        $user = $this->getUser();
        $user2 = $this->getUser();
        $channel = $this->getChannel();
        $msgId = $this->generateGuid();
        $msg = ["id" => $msgId, "text" => "hello world"];
        $response = $channel->sendMessage($msg, $user["id"]);
        $response = $this->client->flagMessage($msgId, ["user_id" => $user2["id"]]);
    }

    public function testUnFlagMessage()
    {
        $user = $this->getUser();
        $user2 = $this->getUser();
        $channel = $this->getChannel();
        $msgId = $this->generateGuid();
        $msg = ["id" => $msgId, "text" => "hello world"];
        $response = $channel->sendMessage($msg, $user["id"]);
        $response = $this->client->flagMessage($msgId, ["user_id" => $user2["id"]]);
        $response = $this->client->unFlagMessage($msgId, ["user_id" => $user2["id"]]);
    }

    public function testQueryMessageFlags()
    {
        $user = $this->getUser();
        $user2 = $this->getUser();
        $channel = $this->getChannel();
        $msgId = $this->generateGuid();
        $channel->sendMessage(["id" => $msgId, "text" => "flag me!"], $user["id"]);
        $this->client->flagMessage($msgId, ["user_id" => $user2["id"]]);

        $response = $this->client->queryMessageFlags(["user_id" => $user["id"], "is_reviewed" => true]);
        $this->assertSame(count($response["flags"]), 0);

        $response = $this->client->queryMessageFlags(["user_id" => $user["id"], "is_reviewed" => false]);
        $this->assertSame(count($response["flags"]), 1);

        $response = $this->client->queryMessageFlags(["user_id" => $user["id"]]);
        $this->assertSame(count($response["flags"]), 1);

        $response = $this->client->queryMessageFlags(["user_id" => ['$in' => [$user["id"]]]]);
        $this->assertSame(count($response["flags"]), 1);

        $response = $this->client->queryMessageFlags(["channel_cid" => $channel->getCID()]);
        $this->assertSame(count($response["flags"]), 1);

        $response = $this->client->queryMessageFlags(["channel_cid" => ['$in' => [$channel->getCID()]]]);
        $this->assertSame(count($response["flags"]), 1);
    }

    public function testQueryUsersYoungHobbits()
    {
        $this->createFellowship();
        $response = $this->client->queryUsers(
            ["race" => ['$eq' => "Hobbit"]],
            ["age" => -1]
        );
        $this->assertSame(count($response["users"]), 4);
        $ages = [];
        foreach ($response["users"] as $user) {
            $ages[] = $user["age"];
        }
        $this->assertEquals([50, 38, 36, 28], $ages);
    }

    public function testQueryChannelsThrowsIfNullConditions()
    {
        $this->expectException(\GetStream\StreamChat\StreamException::class);
        $this->client->queryChannels(null);
    }

    public function testQueryChannelsThrowsIfEmptyConditions()
    {
        $this->expectException(\GetStream\StreamChat\StreamException::class);
        $this->client->queryChannels([]);
    }

    public function testQueryChannelsMembersIn()
    {
        $this->createFellowship();
        $response = $this->client->queryChannels(
            ["members" => ['$in' => ["gimli"]]],
            ["id" => 1]
        );
        $this->assertSame(count($response["channels"]), 1);
        $this->assertSame(count($response["channels"][0]["members"]), 9);
    }

    public function testQueryMembers()
    {
        $bob = ["id" => $this->generateGuid(), "name" => "bob the builder"];
        $bobSponge = ["id" => $this->generateGuid(), "name" => "bob the sponge"];
        $this->client->upsertUsers([$bob, $bobSponge]);
        $channel = $this->client->Channel(
            "messaging",
            $this->generateGuid(),
            ["members" => [$bob["id"], $bobSponge["id"]]]
        );
        $channel->create($bob["id"]);

        $response = $channel->queryMembers();
        $this->assertSame(count($response["members"]), 2);

        $response = $channel->queryMembers(["id" => $bob["id"]]);
        $this->assertSame(count($response["members"]), 1);
        $this->assertSame($response["members"][0]["user"]["id"], $bob["id"]);

        $response = $channel->queryMembers(["name" => ['$autocomplete' => "bob"]], []);
        $this->assertSame(count($response["members"]), 2);

        $response = $channel->queryMembers(["name" => ['$autocomplete' => "bob"]], [], ["limit" => 1]);
        $this->assertSame(count($response["members"]), 1);
    }

    public function testQueryMembersMemberBasedChannel()
    {
        $bob = ["id" => $this->generateGuid(), "name" => "bob the builder"];
        $bobSponge = ["id" => $this->generateGuid(), "name" => "bob the sponge"];
        $this->client->upsertUsers([$bob, $bobSponge]);
        $channel = $this->client->Channel(
            "messaging",
            null,
            ["members" => [$bob["id"], $bobSponge["id"]]]
        );
        $channel->create($bob["id"]);

        $response = $channel->queryMembers(["id" => $bob["id"]]);
        $this->assertSame(count($response["members"]), 1);
        $this->assertSame($response["members"][0]["user"]["id"], $bob["id"]);

        $response = $channel->queryMembers(["name" => ['$autocomplete' => "bob"]], []);
        $this->assertSame(count($response["members"]), 2);

        $response = $channel->queryMembers(["name" => ['$autocomplete' => "bob"]], [], ["limit" => 1]);
        $this->assertSame(count($response["members"]), 1);
    }

    public function testDevices()
    {
        $user = $this->getUser();
        $response = $this->client->getDevices($user["id"]);
        $this->assertTrue(array_key_exists("devices", (array)$response));
        $this->assertSame(count($response["devices"]), 0);
        $this->client->addDevice($this->generateGuid(), "apn", $user["id"]);
        $response = $this->client->getDevices($user["id"]);
        $this->assertSame(count($response["devices"]), 1);
        $response = $this->client->deleteDevice($response["devices"][0]["id"], $user["id"]);
        $response = $this->client->getDevices($user["id"]);
        $this->assertSame(count($response["devices"]), 0);
        // overdoing it a little?
        $this->client->addDevice($this->generateGuid(), "apn", $user["id"]);
        $response = $this->client->getDevices($user["id"]);
        $this->assertSame(count($response["devices"]), 1);
    }

    public function testGetRateLimits()
    {
        $response = $this->client->getRateLimits();
        $this->assertTrue(array_key_exists("server_side", (array)$response));
        $this->assertTrue(array_key_exists("android", (array)$response));
        $this->assertTrue(array_key_exists("ios", (array)$response));
        $this->assertTrue(array_key_exists("web", (array)$response));
        $response = $this->client->getRateLimits(true);
        $this->assertTrue(array_key_exists("server_side", (array)$response));
        $this->assertFalse(array_key_exists("android", (array)$response));
        $this->assertFalse(array_key_exists("ios", (array)$response));
        $this->assertFalse(array_key_exists("web", (array)$response));
        $response = $this->client->getRateLimits(true, true, false, false, ["GetRateLimits", "SendMessage"]);
        $this->assertTrue(array_key_exists("server_side", (array)$response));
        $this->assertTrue(array_key_exists("android", (array)$response));
        $this->assertFalse(array_key_exists("ios", (array)$response));
        $this->assertFalse(array_key_exists("web", (array)$response));
        $this->assertSame(count($response["android"]), 2);
        $this->assertSame(count($response["server_side"]), 2);
        $this->assertSame($response["android"]["GetRateLimits"]["limit"], $response["android"]["GetRateLimits"]["remaining"]);
        $this->assertTrue($response["server_side"]["GetRateLimits"]["limit"] > $response["server_side"]["GetRateLimits"]["remaining"]);
    }

    public function testChannelBanUser()
    {
        $user = $this->getUser();
        $user2 = $this->getUser();
        $channel = $this->getChannel();
        $channel->banUser($user["id"], ["user_id" => $user2["id"]]);
        $channel->banUser($user["id"], [
            "user_id" => $user2["id"],
            "timeout" => 3600,
            "reason" => "offensive language is not allowed here"
        ]);
        $channel->unBanUser($user["id"]);
    }

    public function testChannelCreateWithoutId()
    {
        $user = $this->getUser();
        $user2 = $this->getUser();
        $user_ids = [$user["id"], $user2["id"]];
        $channel = $this->client->Channel(
            "messaging",
            null,
            ["members" => $user_ids]
        );
        $this->assertNull($channel->id);
        $channel->create($this->getUser()["id"]);
        $this->assertNotNull($channel->id);
    }

    public function testChannelSendEvent()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();
        $response = $channel->sendEvent(["type" => "typing.start"], $user["id"]);
        $this->assertTrue(array_key_exists("event", (array)$response));
        $this->assertSame($response["event"]["type"], "typing.start");
    }

    public function testChannelSendReaction()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();

        $msg = $channel->sendMessage(["text" => "hello world"], $user["id"]);
        $response = $channel->sendReaction(
            $msg["message"]["id"],
            ["type" => "love"],
            $user["id"]
        );
        $this->assertTrue(array_key_exists("message", (array)$response));
        $this->assertSame($response["message"]["latest_reactions"][0]["type"], "love");
        $this->assertSame(count($response["message"]["latest_reactions"]), 1);
    }

    public function testChannelDeleteReaction()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();

        $msg = $channel->sendMessage(["text" => "hi"], $user["id"]);
        $response = $channel->sendReaction(
            $msg["message"]["id"],
            ["type" => "love"],
            $user["id"]
        );
        $response = $channel->deleteReaction(
            $msg["message"]["id"],
            "love",
            $user["id"]
        );
        $this->assertTrue(array_key_exists("message", (array)$response));
        $this->assertSame(count($response["message"]["latest_reactions"]), 0);
    }

    public function testChannelUpdate()
    {
        $channel = $this->getChannel();
        $response = $channel->update(["motd" => "one apple a day"]);
        $this->assertTrue(array_key_exists("channel", (array)$response));
        $this->assertSame($response["channel"]["motd"], "one apple a day");
    }

    public function testInviteAndAccept()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();
        $channel->inviteMembers([$user["id"]]);
        $channel->acceptInvite($user["id"]);
    }
    
    public function testInviteAndReject()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();
        $channel->inviteMembers([$user["id"]]);
        $channel->rejectInvite($user["id"]);
    }

    public function testChannelUpdatePartial()
    {
        $channel = $this->getChannel();

        try {
            $channel->updatePartial();
            $this->fail("Missing set/unset exception isn't thrown");
        } catch (StreamException $e) {
            $this->assertEquals("set or unset is required", $e->getMessage());
        }

        $set = [
            "config_overrides" => ["replies" => false],
            "motd" => "one apple a day"
        ];

        $response = $channel->updatePartial($set);
        $this->assertTrue(array_key_exists("channel", (array)$response));
        $this->assertSame($response["channel"]["motd"], "one apple a day");

        $response = $channel->updatePartial(null, ["motd"]);
        $this->assertTrue(array_key_exists("channel", (array)$response));
        $this->assertFalse(array_key_exists("motd", $response["channel"]));
    }

    public function testChannelDelete()
    {
        $channel = $this->getChannel();
        $response = $channel->delete();
        $this->assertTrue(array_key_exists("channel", (array)$response));
        $this->assertNotNull($response["channel"]["deleted_at"]);
    }

    public function testChannelTruncate()
    {
        $channel = $this->getChannel();
        $response = $channel->truncate();
        $this->assertTrue(array_key_exists("channel", (array)$response));
    }

    public function testChannelTruncateWithOptions()
    {
        $channel = $this->getChannel();
        $truncateOpts = [
            "message" => ["text" => "Truncating channel", "user_id" => $this->getUser()["id"]],
            "skip_push" => true,
        ];
        $response = $channel->truncate($truncateOpts);
        $this->assertTrue(array_key_exists("channel", (array)$response));
    }

    public function testChannelAddMembers()
    {
        $user1 = $this->getUser();
        $user2 = $this->getUser();
        $channel = $this->getChannel();
        $response = $channel->removeMembers([$user1["id"]]);
        $this->assertTrue(array_key_exists("members", (array)$response));
        $this->assertSame(count($response["members"]), 0);

        $response = $channel->addMembers([$user1["id"]]);
        $response = $channel->addMembers([$user2["id"]], ["hide_history" => true]);

        $this->assertTrue(array_key_exists("members", (array)$response));
        $this->assertSame(count($response["members"]), 2);
        if (array_key_exists("is_moderator", $response["members"][0])) {
            $this->assertFalse($response["members"][0]["is_moderator"]);
        }
    }

    public function testChannelAddModerators()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();
        $response = $channel->addModerators([$user["id"]]);
        $this->assertTrue($response["members"][0]["is_moderator"]);

        $response = $channel->demoteModerators([$user["id"]]);
        if (array_key_exists("is_moderator", $response["members"][0])) {
            $this->assertFalse($response["members"][0]["is_moderator"]);
        }
    }

    public function testChannelMarkRead()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();
        $response = $channel->markRead($user["id"]);
        $this->assertTrue(array_key_exists("event", (array)$response));
        $this->assertSame($response["event"]["type"], "message.read");
    }

    public function testChannelGetReplies()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();

        $msg = $channel->sendMessage(["text" => "hi"], $user["id"]);
        $response = $channel->getReplies($msg["message"]["id"]);
        $this->assertTrue(array_key_exists("messages", (array)$response));
        $this->assertSame(count($response["messages"]), 0);
        for ($i=0;$i<10;$i++) {
            $rpl = $channel->sendMessage(
                [
                    "text" => "hi",
                    "index" => $i,
                    "parent_id" => $msg["message"]["id"]
                ],
                $user["id"]
            );
        }
        $response = $channel->getReplies($msg["message"]["id"]);
        $this->assertSame(count($response["messages"]), 10);

        $response = $channel->getReplies(
            $msg["message"]["id"],
            [
                "limit" => 3,
                "offset" => 3]
        );
        $this->assertSame(count($response["messages"]), 3);
        $this->assertSame($response["messages"][0]["index"], 7);
    }

    public function testChannelGetReactions()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();

        $msg = $channel->sendMessage(["text" => "hi"], $user["id"]);
        $response = $channel->getReactions($msg["message"]["id"]);
        $this->assertTrue(array_key_exists("reactions", (array)$response));
        $this->assertSame(count($response["reactions"]), 0);

        $channel->sendReaction(
            $msg["message"]["id"],
            [
                "type" => "love",
                "count" => 42
            ],
            $user["id"]
        );

        $channel->sendReaction(
            $msg["message"]["id"],
            [
                "type" => "clap",
            ],
            $user["id"]
        );

        $response = $channel->getReactions($msg["message"]["id"]);
        $this->assertSame(count($response["reactions"]), 2);

        $response = $channel->getReactions(
            $msg["message"]["id"],
            [
                "offset" => 1]
        );
        $this->assertSame(count($response["reactions"]), 1);
        $this->assertSame($response["reactions"][0]["count"], 42);
    }

    public function testSearch()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();
        $query = "supercalifragilisticexpialidocious";
        $channel->sendMessage(["text" => "How many syllables are there in " . $query . "?"], $user["id"]);
        $channel->sendMessage(["text" => "Does 'cious' count as one or two?"], $user["id"]);
        $response = $this->client->search(
            ["type" => "messaging"],
            $query,
            ["limit" => 2, "offset" => 0]
        );
        // searches all channels so make sure at least one is found
        $this->assertTrue(count($response['results']) >= 1);
        $this->assertTrue(strpos($response['results'][0]['message']['text'], $query)!==false);
        $response = $this->client->search(
            ["type" => "messaging"],
            "cious",
            ["limit" => 12, "offset" => 0]
        );
        foreach ($response['results'] as $message) {
            $this->assertFalse(strpos($message['message']['text'], $query));
        }

        $response = $this->client->search(
            ["cid" => $channel->getCID()],
            ['text' => ['$q' => 'cious']],
            ["limit" => 1, "offset" => 0]
        );
        $this->assertSame(count($response['results']), 1);
    }

    public function testSearchOffsetAndSortFails()
    {
        $this->expectException(\GetStream\StreamChat\StreamException::class);
        $query = "supercalifragilisticexpialidocious";
        $this->client->search(
            ["type" => "messaging"],
            $query,
            ["sort" => [["created_at"=>-1]], "offset" => 1]
        );
    }

    public function testSearchOffsetAndNextFails()
    {
        $this->expectException(\GetStream\StreamChat\StreamException::class);
        $query = "supercalifragilisticexpialidocious";
        $this->client->search(
            ["type" => "messaging"],
            $query,
            ["next" => $query, "offset" => 1]
        );
    }


    public function testSearchWithSort()
    {
        $this->markTestSkipped();
        $user = $this->getUser();
        $channel = $this->getChannel();
        $query = "supercalifragilisticexpialidocious";
        $channel->sendMessage(["text" => "How many syllables are there in " . $query . "?"], $user["id"]);
        $channel->sendMessage(["text" => "Does ". $query . " count as one or two?"], $user["id"]);
        $response = $this->client->search(
            ["type" => "messaging"],
            $query,
            ["sort"=> [["created_at"=> -1]], "limit" => 1]
        );
        // searches all channels so make sure at least one is found
        $this->assertTrue(count($response['results']) >= 1);
        $this->assertTrue(strpos($response['results'][0]['message']['text'], $query)!==false);
        $response = $this->client->search(
            ["type" => "messaging"],
            $query,
            ["limit" => 1, "next"=> $response['next']]
        );
        $this->assertTrue(count($response['results']) >= 1);
        $this->assertTrue(strpos($response['results'][0]['message']['text'], $query)!==false);
    }


    public function testGetMessage()
    {
        $user = $this->getUser();
        $channel = $this->getChannel();
        $org = $channel->sendMessage(["text" => "hi"], $user["id"])['message'];
        $msg = $this->client->getMessage($org["id"])['message'];
        $this->assertSame($msg['id'], $org['id']);
        $this->assertSame($msg['text'], $org['text']);
        $this->assertSame($msg['user']['id'], $org['user']['id']);
    }

    public function testChannelSendAndDeleteFile()
    {
        $url = "https://stream-blog-v2.imgix.net/blog/wp-content/uploads/1f4a0a19b7533494c5341170abbf655e/stream_logo.svg";
        $user = $this->getUser();
        $channel = $this->getChannel();
        $resp = $channel->sendFile($url, "logo.svg", $user);
        $this->assertTrue(strpos($resp['file'], "logo.svg")!==false);
        $resp = $channel->deleteFile($resp['file']);
    }

    public function testChannelSendAndDeleteImage()
    {
        $url = "https://getstream.io/images/icons/favicon-32x32.png";
        $user = $this->getUser();
        $channel = $this->getChannel();
        $resp = $channel->sendImage($url, "logo.png", $user);
        $this->assertTrue(strpos($resp['file'], "logo.png")!==false);
        // $resp = $channel->deleteImage($resp['file']);
    }

    public function testChannelHideShow()
    {
        // setup
        $user1 = $this->getUser();
        $user2 = $this->getUser();
        $channel = $this->getChannel();
        $channel->addMembers([$user1['id'], $user2['id']]);
        // verify
        $response = $this->client->queryChannels(["id" => $channel->id]);
        $this->assertSame(count($response["channels"]), 1);
        $response = $this->client->queryChannels(["id" => $channel->id], null, ['user_id' => $user1["id"]]);
        $this->assertSame(count($response["channels"]), 1);
        // hide
        $response = $channel->hide($user1['id']);
        $response = $this->client->queryChannels(["id" => $channel->id], null, ['user_id' => $user1["id"]]);
        $this->assertSame(count($response["channels"]), 0);
        // search hidden channels
        $response = $this->client->queryChannels(["id" => $channel->id, "hidden" => true], null, ['user_id' => $user1["id"]]);
        $this->assertSame(count($response["channels"]), 1);
        // unhide
        $response = $channel->show($user1['id']);
        $response = $this->client->queryChannels(["id" => $channel->id], null, ['user_id' => $user1["id"]]);
        $this->assertSame(count($response["channels"]), 1);
        // hide again
        $response = $channel->hide($user1['id']);
        $response = $this->client->queryChannels(["id" => $channel->id], null, ['user_id' => $user1["id"]]);
        $this->assertSame(count($response["channels"]), 0);
        // send message
        $msgId = $this->generateGuid();
        $msg = ["id" => $msgId, "text" => "hello world"];
        $response = $channel->sendMessage($msg, $user2["id"]);
        // channel should be 'visible'
        $response = $this->client->queryChannels(["id" => $channel->id], null, ['user_id' => $user1["id"]]);
        $this->assertSame(count($response["channels"]), 1);
    }

    public function testPartialUpdateUsers()
    {
        $carmen = ["id" => $this->generateGuid(), "name" => "Carmen SanDiego", "hat" => "blue", "location" => "Here"];
        $response = $this->client->upsertUser($carmen);
        $this->assertTrue(array_key_exists("users", (array)$response));
        $this->assertTrue(array_key_exists($carmen["id"], $response["users"]));
        $this->assertSame($response["users"][$carmen["id"]]["hat"], "blue");
        $response = $this->client->partialUpdateUser(["id" => $carmen["id"], "set" => ["hat" => "red"]]);
        $response = $this->client->queryUsers(["id" => $carmen["id"]]);
        $this->assertSame($response["users"][0]["hat"], "red");
        $this->assertSame($response["users"][0]["location"], "Here");
        $wally = ["id" => $this->generateGuid(), "name" => "Wally", "shirt" => "white", "location" => "There"];
        $response = $this->client->upsertUser($wally);
        $response = $this->client->partialUpdateUsers([
            ["id" => $carmen["id"], "set" => ["coat" => "red"], "unset" => ["location"]],
            ["id" => $wally["id"], "set" => ["shirt" => "striped"], "unset" => ["location"]],
        ]);
        $response = $this->client->queryUsers(["id" => $carmen["id"]]);
        $this->assertSame($response["users"][0]["hat"], "red");
        $this->assertSame($response["users"][0]["coat"], "red");
        $this->assertFalse(array_key_exists("location", $response["users"][0]));
        $response = $this->client->queryUsers(["id" => $wally["id"]]);
        $this->assertSame($response["users"][0]["shirt"], "striped");
        $this->assertFalse(array_key_exists("location", $response["users"][0]));
    }

    public function testChannelMuteUnmute()
    {
        // setup
        $user1 = $this->getUser();
        $channel = $this->getChannel();
        $channel->addMembers([$user1['id']]);
        // verify
        $response = $this->client->queryChannels(["id" => $channel->id]);
        $this->assertSame(count($response["channels"]), 1);
        $response = $this->client->queryChannels(["id" => $channel->id], null, ['user_id' => $user1["id"]]);
        $this->assertSame(count($response["channels"]), 1);
        // mute
        $channel->mute($user1['id']);
        $response = $this->client->queryChannels(["muted" => true, "cid" => $channel->getCID()], null, ["user_id" => $user1["id"]]);
        $this->assertSame(count($response["channels"]), 1);
        // unmute
        $channel->unmute($user1['id']);
        $response = $this->client->queryChannels(["muted" => true, "cid" => $channel->getCID()], null, ["user_id" => $user1["id"]]);
        $this->assertSame(count($response["channels"]), 0);
        // mute with expiration
        $channel->mute($user1['id'], 10000);
        $response = $this->client->queryChannels(["muted" => true, "cid" => $channel->getCID()], null, ["user_id" => $user1["id"]]);
        $this->assertSame(count($response["channels"]), 1);
        sleep(10);
        $response = $this->client->queryChannels(["muted" => true, "cid" => $channel->getCID()], null, ["user_id" => $user1["id"]]);
        $this->assertSame(count($response["channels"]), 0);
    }

    public function testCreateAndDeleteRole()
    {
        try {
            $this->client->deleteRole("test-php-sdk-role");
            sleep(15);
        } catch (\Exception $e) {
        }
        $response = $this->client->createRole("test-php-sdk-role");
        $this->assertEquals("test-php-sdk-role", $response['role']['name']);
        sleep(15);
        $response = $this->client->listRoles();
        $found = false;
        foreach ($response['roles'] as $role) {
            if ($role['name'] == "test-php-sdk-role") {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
        $this->client->deleteRole("test-php-sdk-role");
    }

    public function testPermissions()
    {
        $response = $this->client->listPermissions();
        $this->assertNotEmpty($response['permissions']);
        $response = $this->client->getPermission("read-channel");
        $this->assertEquals("read-channel", $response['permission']['id']);
    }
}
