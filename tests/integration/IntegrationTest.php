<?php

declare(strict_types=0);

namespace GetStream\Integration;

use GetStream\StreamChat\Client;
use GetStream\StreamChat\StreamException;
use GuzzleHttp\Client as GuzzleClient;
use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $user1;

    /**
     * @var array
     */
    protected $user2;

    /**
     * @var \GetStream\StreamChat\Channel
     */
    protected $channel;

    protected function setUp(): void
    {
        $this->client = new Client(getenv('STREAM_KEY'), getenv('STREAM_SECRET'), null, null, 10.0);
        $this->user1 = $this->getUser();
        $this->user2 = $this->getUser();
        $this->channel = $this->getChannel();
    }

    protected function tearDown(): void
    {
        try {
            $this->channel->delete();
            $this->client->deleteUser($this->user1['id'], ["user" => "hard", "messages" => "hard"]);
            $this->client->deleteUser($this->user2['id'], ["user" => "hard", "messages" => "hard"]);
        } catch (\Exception $e) {
            // We don't care about cleanup errors
            // They're mostly throttlings
        }
    }

    private function getUser(): array
    {
        // this creates a user on the server
        $user = ["id" => $this->generateGuid()];
        $response = $this->client->upsertUser($user);
        $this->assertTrue(array_key_exists("users", (array)$response));
        $this->assertTrue(array_key_exists($user["id"], $response["users"]));
        return $user;
    }

    public function getChannel(): \GetStream\StreamChat\Channel
    {
        $channel = $this->client->Channel(
            "messaging",
            $this->generateGuid(),
            ["test" => true, "language" => "php"]
        );
        $channel->create($this->user1["id"]);
        return $channel;
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

    // Disabling following test sincewe don't add rate limits from backend anymore
    // for non-limited api calls
    //
    // public function testStreamResponse()
    // {
    //     $response = $this->client->getAppSettings();
    //     $rateLimits = $response->getRateLimits();

    //     $this->assertEquals(200, $response->getStatusCode());
    //     $this->assertGreaterThan(0, $rateLimits->getLimit());
    //     $this->assertGreaterThan(0, $rateLimits->getRemaining());
    //     $this->assertNotNull($rateLimits->getReset());

    //     $serialized = json_encode($response);
    //     $this->assertFalse(str_contains($serialized, "rate"));
    //     $this->assertTrue(str_starts_with($serialized, '{"app"'));
    // }

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
        $response = $this->client->muteUser($this->user1["id"], $this->user2["id"]);
        $this->assertTrue(array_key_exists("mute", (array)$response));
        $this->assertSame($response["mute"]["target"]["id"], $this->user1["id"]);
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
        $channel = $this->getChannel();
        $response = $this->channel->sendMessage(["text" => "How many syllables are there in xyz"], $this->user1["id"]);

        $pushResponse = $this->client->checkPush(["message_id" => $response["message"]["id"], "skip_devices" => true, "user_id" => $this->user1["id"]]);

        $this->assertTrue(array_key_exists("rendered_message", (array)$pushResponse));
        $channel->delete();
    }

    public function testCheckSqs()
    {
        $response = $this->client->checkSqs([
            "sqs_url" => "https://foo.com/bar",
            "sqs_key" => "key",
            "sqs_secret" => "secret"
        ]);

        $this->assertTrue(array_key_exists("status", (array)$response));
    }

    public function testCheckSns()
    {
        $response = $this->client->checkSns([
            "sns_topic_arn" => "arn:aws:sns:us-east-1:123456789012:sns-topic",
            "sns_key" => "key",
            "sns_secret" => "secret"
        ]);

        $this->assertTrue(array_key_exists("status", (array)$response));
    }

    public function testGuestUser()
    {
        try {
            $id = $this->generateGuid();
            $response = $this->client->setGuestUser(["user" => ["id" => $id]]);
            $this->client->deleteUser($id, ["user" => "hard"]);
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
        $this->client->deleteUser($user["id"], ["user" => "hard", "messages" => "hard"]);
    }

    public function testUpsertUsers()
    {
        $user = ["id" => $this->generateGuid()];
        $response = $this->client->upsertUsers([$user]);
        $this->assertTrue(array_key_exists("users", (array)$response));
        $this->assertTrue(array_key_exists($user["id"], $response["users"]));
        $this->client->deleteUser($user["id"], ["user" => "hard", "messages" => "hard"]);
    }

    public function testDeleteUser()
    {
        $response = $this->client->deleteUser($this->user1["id"], ["user" => "hard", "messages" => "hard"]);
        $this->assertTrue(array_key_exists("user", (array)$response));
        $this->assertSame($this->user1["id"], $response["user"]["id"]);
    }

    public function testDeleteUsers()
    {
        $user = $this->getUser();
        $response = $this->client->deleteUsers([$user["id"]], ["user" => "hard", "messages" => "hard"]);
        $this->assertTrue(array_key_exists("task_id", (array)$response));
        $taskId = $response["task_id"];

        // Since we don't want to test the backend functionality, just
        // the SDK functionality, we don't care whether it succeeded it or not.
        // Just make sure the method functions properly.
        $response = $this->client->getTask($taskId);
        $this->assertTrue(array_key_exists("status", (array)$response));
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
        for ($i = 0; $i < 30; $i++) {
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
        $response = $this->client->deactivateUser($this->user1["id"]);
        $this->assertTrue(array_key_exists("user", (array)$response));
        $this->assertSame($this->user1["id"], $response["user"]["id"]);
    }

    public function testDeactivateReactivateUsers()
    {
        $user = $this->getUser();
        $response = $this->client->deactivateUsers([$user["id"]]);
        $this->assertTrue(array_key_exists("task_id", (array)$response));
        $taskId = $response["task_id"];

        for ($i = 0; $i < 30; $i++) {
            $response = $this->client->getTask($taskId);
            if ($response["status"] == "completed") {
                break;
            }
            usleep(300000);
        }

        // Since we don't want to test the backend functionality, just
        // the SDK functionality, we don't care whether it succeeded it or not.
        // Just make sure the method functions properly.
        $response = $this->client->reactivateUsers([$user["id"]]);
        $this->assertTrue(array_key_exists("task_id", (array)$response));
    }

    public function testReactivateUser()
    {
        $response = $this->client->deactivateUser($this->user1["id"]);
        $this->assertTrue(array_key_exists("user", (array)$response));
        $response = $this->client->reactivateUser($this->user1["id"]);
        $this->assertTrue(array_key_exists("user", (array)$response));
        $this->assertSame($this->user1["id"], $response["user"]["id"]);
    }

    public function testReactivateUserError()
    {
        $this->expectException(\GetStream\StreamChat\StreamException::class);
        $this->client->reactivateUser($this->user1["id"]);
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
        $response = $this->channel->sendMessage(["text" => "hello world"], $this->user1["id"]);
        $this->assertFalse($response["message"]["shadowed"]);
        $response = $this->client->getMessage($response["message"]["id"]);
        $this->assertFalse($response["message"]["shadowed"]);

        $this->client->shadowBan($this->user1["id"], ["user_id" => $this->user2["id"]]);

        $response = $this->channel->sendMessage(["text" => "hello world"], $this->user1["id"]);
        $this->assertFalse($response["message"]["shadowed"]);
        $response = $this->client->getMessage($response["message"]["id"]);
        $this->assertTrue($response["message"]["shadowed"]);

        $this->client->removeShadowBan($this->user1["id"], ["user_id" => $this->user2["id"]]);

        $response = $this->channel->sendMessage(["text" => "hello world"], $this->user1["id"]);
        $this->assertFalse($response["message"]["shadowed"]);
        $response = $this->client->getMessage($response["message"]["id"]);
        $this->assertFalse($response["message"]["shadowed"]);
    }

    public function testPinMessage()
    {
        $response = $this->channel->sendMessage(["text" => "hello world"], $this->user1["id"]);
        $this->assertNull($response["message"]["pinned_at"]);
        $this->assertNull($response["message"]["pinned_by"]);

        $this->client->pinMessage($response["message"]["id"], $this->user2["id"]);

        $response = $this->client->getMessage($response["message"]["id"]);
        $this->assertNotNull($response["message"]["pinned_at"]);
        $this->assertEquals($this->user2["id"], $response["message"]["pinned_by"]["id"]);

        $this->client->unPinMessage($response["message"]["id"], $this->user2["id"]);

        $response = $this->client->getMessage($response["message"]["id"]);
        $this->assertNull($response["message"]["pinned_at"]);
        $this->assertNull($response["message"]["pinned_by"]);
    }

    public function testBanUser()
    {
        $this->client->banUser($this->user1["id"], ["user_id" => $this->user2["id"]]);
    }

    public function testUnBanUser()
    {
        $this->client->banUser($this->user1["id"], ["user_id" => $this->user2["id"]]);
        $this->client->unBanUser($this->user1["id"], ["user_id" => $this->user2["id"]]);
    }

    public function testQueryBannedUsers()
    {
        $this->client->banUser($this->user1["id"], ["user_id" => $this->user2["id"], "reason" => "because"]);

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
        $msgId = $this->generateGuid();
        $this->channel->sendMessage(["id" => $msgId, "text" => "/giphy wave"], $this->user1["id"]);

        $response = $this->client->sendMessageAction($msgId, $this->user1["id"], ["image_action" => "shuffle"]);

        $this->assertTrue(array_key_exists("message", (array)$response));
    }

    public function testTranslateMessage()
    {
        $msgId = $this->generateGuid();
        $this->channel->sendMessage(["id" => $msgId, "text" => "hello world"], $this->user1["id"]);

        $response = $this->client->translateMessage($msgId, "hu");

        $this->assertTrue(array_key_exists("message", (array)$response));
    }

    public function testFlagUser()
    {
        $this->client->flagUser($this->user1["id"], ["user_id" => $this->user2["id"]]);
    }

    public function testUnFlagUser()
    {
        $this->client->flagUser($this->user1["id"], ["user_id" => $this->user2["id"]]);
        $this->client->unFlagUser($this->user1["id"], ["user_id" => $this->user2["id"]]);
    }

    public function testQueryFlagAndReviewFlag()
    {
        $msgId = $this->generateGuid();
        $msg = ["id" => $msgId, "text" => "helloworld"];
        $this->channel->sendMessage($msg, $this->user1["id"]);
        $this->client->flagMessage($msgId, ["user_id" => $this->user2["id"]]);

        $response = $this->client->queryFlagReports(["message_id" => $msgId]);
        $this->assertSame(count($response["flag_reports"]), 1);

        $response = $this->client->reviewFlagReport($response["flag_reports"][0]["id"], "reviewed", $this->user1["id"], []);
        $this->assertNotNull($response["flag_report"]);
    }

    public function testMarkAllRead()
    {
        $this->client->markAllRead($this->user1["id"]);
    }

    public function testChannelWithoutData()
    {
        $channel = $this->client->Channel(
            "messaging",
            $this->generateGuid()
        );
        $channel->create($this->user1["id"]);
        $channel->delete();
    }

    public function testGetChannelWithoutData()
    {
        $channel = $this->client->Channel(
            "messaging",
            $this->generateGuid()
        );
        $channel->create($this->user1["id"]);
        $channel->delete();
    }

    public function testUpdateMessage()
    {
        $msgId = $this->generateGuid();
        $msg = ["id" => $msgId, "text" => "hello world"];
        $response = $this->channel->sendMessage($msg, $this->user1["id"], null, ["skip_push" => true]);
        $this->assertSame("hello world", $response["message"]["text"]);
        $msg = [
            "id" => $msgId,
            "text" => "hello world",
            "awesome" => true,
            "user" => ["id" => $response["message"]["user"]["id"]]
        ];
        $this->client->updateMessage($msg);
    }

    public function testPendingMessage()
    {
        $msgId = $this->generateGuid();
        $msg = ["id" => $msgId, "text" => "hello world"];
        $response1 = $this->channel->sendMessage($msg, $this->user1["id"], null, ["pending" => true]);
        $this->assertSame($msgId, $response1["message"]["id"]);
        
        $response = $this->client->queryChannels(["id" => $this->channel->id], null, ['user_id' => $this->user1["id"]]);
        // check if length of $response["channels"][0]['pending_messages']) is 1
        $this->assertSame(1, sizeof($response["channels"][0]['pending_messages']));


        $response2 = $this->client->commitMessage($msgId);
        $this->assertSame($msgId, $response2["message"]["id"]);
    }

    public function testDeleteMessage()
    {
        $msgId = $this->generateGuid();
        $msg = ["id" => $msgId, "text" => "helloworld"];
        $this->channel->sendMessage($msg, $this->user1["id"]);
        $this->client->deleteMessage($msgId);
    }

    public function testManyMessages()
    {
        $msgId = $this->generateGuid();
        $msg = ["id" => $msgId, "text" => "helloworld"];
        $this->channel->sendMessage($msg, $this->user1["id"]);

        $msgResponse = $this->channel->getManyMessages([$msgId]);

        $this->assertTrue(array_key_exists("messages", (array)$msgResponse));
    }

    public function testFlagMessage()
    {
        $msgId = $this->generateGuid();
        $msg = ["id" => $msgId, "text" => "hello world"];
        $this->channel->sendMessage($msg, $this->user1["id"]);
        $this->client->flagMessage($msgId, ["user_id" => $this->user2["id"]]);
    }

    public function testUnFlagMessage()
    {
        $msgId = $this->generateGuid();
        $msg = ["id" => $msgId, "text" => "hello world"];
        $this->channel->sendMessage($msg, $this->user1["id"]);
        $this->client->flagMessage($msgId, ["user_id" => $this->user2["id"]]);
        $this->client->unFlagMessage($msgId, ["user_id" => $this->user2["id"]]);
    }

    public function testQueryMessageFlags()
    {
        $msgId = $this->generateGuid();
        $this->channel->sendMessage(["id" => $msgId, "text" => "flag me!"], $this->user1["id"]);
        $this->client->flagMessage($msgId, ["user_id" => $this->user2["id"]]);

        $response = $this->client->queryMessageFlags(["user_id" => $this->user1["id"], "is_reviewed" => true]);
        $this->assertSame(count($response["flags"]), 0);

        $response = $this->client->queryMessageFlags(["user_id" => $this->user1["id"], "is_reviewed" => false]);
        $this->assertSame(count($response["flags"]), 1);

        $response = $this->client->queryMessageFlags(["user_id" => $this->user1["id"]]);
        $this->assertSame(count($response["flags"]), 1);

        $response = $this->client->queryMessageFlags(["user_id" => ['$in' => [$this->user1["id"]]]]);
        $this->assertSame(count($response["flags"]), 1);

        $response = $this->client->queryMessageFlags(["channel_cid" => $this->channel->getCID()]);
        $this->assertSame(count($response["flags"]), 1);

        $response = $this->client->queryMessageFlags(["channel_cid" => ['$in' => [$this->channel->getCID()]]]);
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
        $this->client->queryChannels([]);
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

        $this->client->deleteUser($bob["id"], ["user" => "hard", "messages" => "hard"]);
        $this->client->deleteUser($bobSponge["id"], ["user" => "hard", "messages" => "hard"]);
        $channel->delete();
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

        $this->client->deleteUser($bob["id"], ["user" => "hard", "messages" => "hard"]);
        $this->client->deleteUser($bobSponge["id"], ["user" => "hard", "messages" => "hard"]);
        $channel->delete();
    }

    public function testDevices()
    {
        $response = $this->client->getDevices($this->user1["id"]);
        $this->assertTrue(array_key_exists("devices", (array)$response));
        $this->assertSame(count($response["devices"]), 0);
        $this->client->addDevice($this->generateGuid(), "apn", $this->user1["id"]);
        $response = $this->client->getDevices($this->user1["id"]);
        $this->assertSame(count($response["devices"]), 1);
        $response = $this->client->deleteDevice($response["devices"][0]["id"], $this->user1["id"]);
        $response = $this->client->getDevices($this->user1["id"]);
        $this->assertSame(count($response["devices"]), 0);
        // overdoing it a little?
        $this->client->addDevice($this->generateGuid(), "apn", $this->user1["id"]);
        $response = $this->client->getDevices($this->user1["id"]);
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
        $this->channel->banUser($this->user1["id"], ["user_id" => $this->user2["id"]]);
        $this->channel->banUser($this->user1["id"], [
            "user_id" => $this->user2["id"],
            "timeout" => 3600,
            "reason" => "offensive language is not allowed here"
        ]);
        $this->channel->unBanUser($this->user1["id"]);
    }

    public function testChannelCreateWithoutId()
    {
        $user_ids = [$this->user1["id"], $this->user2["id"]];
        $channel = $this->client->Channel(
            "messaging",
            null,
            ["members" => $user_ids]
        );
        $this->assertNull($channel->id);
        $channel->create($this->user1["id"]);
        $this->assertNotNull($channel->id);
        $channel->delete();
    }

    public function testChannelSendEvent()
    {
        $response = $this->channel->sendEvent(["type" => "typing.start"], $this->user1["id"]);
        $this->assertTrue(array_key_exists("event", (array)$response));
        $this->assertSame($response["event"]["type"], "typing.start");
    }

    public function testCustomEvent()
    {
        $this->client->sendUserCustomEvent($this->user1["id"], ["type" => "friendship_request"]);
    }

    public function testChannelSendReaction()
    {
        $msg = $this->channel->sendMessage(["text" => "hello world"], $this->user1["id"]);
        $response = $this->channel->sendReaction(
            $msg["message"]["id"],
            ["type" => "love"],
            $this->user1["id"]
        );
        $this->assertTrue(array_key_exists("message", (array)$response));
        $this->assertSame($response["message"]["latest_reactions"][0]["type"], "love");
        $this->assertSame(count($response["message"]["latest_reactions"]), 1);
    }

    public function testChannelDeleteReaction()
    {
        $msg = $this->channel->sendMessage(["text" => "hi"], $this->user1["id"]);
        $response = $this->channel->sendReaction(
            $msg["message"]["id"],
            ["type" => "love"],
            $this->user1["id"]
        );
        $response = $this->channel->deleteReaction(
            $msg["message"]["id"],
            "love",
            $this->user1["id"]
        );
        $this->assertTrue(array_key_exists("message", (array)$response));
        $this->assertSame(count($response["message"]["latest_reactions"]), 0);
    }

    public function testChannelUpdate()
    {
        $response = $this->channel->update(["motd" => "one apple a day"]);
        $this->assertTrue(array_key_exists("channel", (array)$response));
        $this->assertSame($response["channel"]["motd"], "one apple a day");
    }

    public function testInviteAndAccept()
    {
        $this->channel->inviteMembers([$this->user1["id"]]);
        $this->channel->acceptInvite($this->user1["id"]);
    }

    public function testInviteAndReject()
    {
        $this->channel->inviteMembers([$this->user1["id"]]);
        $this->channel->rejectInvite($this->user1["id"]);
    }

    public function testChannelUpdatePartial()
    {
        try {
            $this->channel->updatePartial();
            $this->fail("Missing set/unset exception isn't thrown");
        } catch (StreamException $e) {
            $this->assertEquals("set or unset is required", $e->getMessage());
        }

        $set = [
            "config_overrides" => ["replies" => false],
            "motd" => "one apple a day"
        ];

        $response = $this->channel->updatePartial($set);
        $this->assertTrue(array_key_exists("channel", (array)$response));
        $this->assertSame($response["channel"]["motd"], "one apple a day");

        $response = $this->channel->updatePartial(null, ["motd"]);
        $this->assertTrue(array_key_exists("channel", (array)$response));
        $this->assertFalse(array_key_exists("motd", $response["channel"]));
    }

    public function testChannelDelete()
    {
        $response = $this->channel->delete();
        $this->assertTrue(array_key_exists("channel", (array)$response));
        $this->assertNotNull($response["channel"]["deleted_at"]);
    }

    public function testChannelTruncate()
    {
        $response = $this->channel->truncate();
        $this->assertTrue(array_key_exists("channel", (array)$response));
    }

    public function testChannelTruncateWithOptions()
    {
        $truncateOpts = [
            "message" => ["text" => "Truncating channel", "user_id" => $this->user1["id"]],
            "skip_push" => true,
        ];
        $response = $this->channel->truncate($truncateOpts);
        $this->assertTrue(array_key_exists("channel", (array)$response));
    }

    public function testChannelAddMembers()
    {
        $response = $this->channel->removeMembers([$this->user1["id"]]);
        $this->assertTrue(array_key_exists("members", (array)$response));
        $this->assertSame(count($response["members"]), 0);

        $response = $this->channel->addMembers([$this->user1["id"]]);
        $response = $this->channel->addMembers([$this->user2["id"]], ["hide_history" => true]);

        $this->assertTrue(array_key_exists("members", (array)$response));
        $this->assertSame(count($response["members"]), 2);
        if (array_key_exists("is_moderator", $response["members"][0])) {
            $this->assertFalse($response["members"][0]["is_moderator"]);
        }
    }

    public function testChannelAssignRoles()
    {
        // Add user to the channel with role set
        $this->client->upsertUsers([
            ['id' => 'james_bond', 'role' => 'user'],
        ]);

        $this->channel->addMembers([
            ['user_id' => 'james_bond', 'channel_role' => 'channel_member'],
        ]);

        $result = $this->channel->assignRoles([
            ['user_id' => 'james_bond', 'channel_role' => 'channel_moderator'],
        ]);

        if (array_key_exists("channel_role", $result["members"][0])) {
            $this->assertEquals('channel_moderator', $result["members"][0]["channel_role"]);
        }

        $result = $this->channel->assignRoles([
            ['user_id' => 'james_bond', 'channel_role' => 'channel_member'],
        ]);

        if (array_key_exists("channel_role", $result["members"][0])) {
            $this->assertEquals('channel_member', $result["members"][0]["channel_role"]);
        }
    }


    public function testChannelAddModerators()
    {
        $response = $this->channel->addModerators([$this->user1["id"]]);
        $this->assertTrue($response["members"][0]["is_moderator"]);

        $response = $this->channel->demoteModerators([$this->user1["id"]]);
        if (array_key_exists("is_moderator", $response["members"][0])) {
            $this->assertFalse($response["members"][0]["is_moderator"]);
        }
    }

    public function testChannelMarkRead()
    {
        $response = $this->channel->markRead($this->user1["id"]);
        $this->assertTrue(array_key_exists("event", (array)$response));
        $this->assertSame($response["event"]["type"], "message.read");
    }

    public function testChannelGetReplies()
    {
        $msg = $this->channel->sendMessage(["text" => "hi"], $this->user1["id"]);
        $response = $this->channel->getReplies($msg["message"]["id"]);
        $this->assertTrue(array_key_exists("messages", (array)$response));
        $this->assertSame(count($response["messages"]), 0);
        for ($i = 0; $i < 10; $i++) {
            $this->channel->sendMessage(
                [
                    "text" => "hi",
                    "index" => $i,
                    "parent_id" => $msg["message"]["id"]
                ],
                $this->user1["id"]
            );
        }
        $response = $this->channel->getReplies($msg["message"]["id"]);
        $this->assertSame(count($response["messages"]), 10);

        $response = $this->channel->getReplies(
            $msg["message"]["id"],
            [
                "limit" => 3,
                "offset" => 3
            ]
        );
        $this->assertSame(count($response["messages"]), 3);
        $this->assertSame($response["messages"][0]["index"], 7);
    }

    public function testChannelGetReactions()
    {
        $msg = $this->channel->sendMessage(["text" => "hi"], $this->user1["id"]);
        $response = $this->channel->getReactions($msg["message"]["id"]);
        $this->assertTrue(array_key_exists("reactions", (array)$response));
        $this->assertSame(count($response["reactions"]), 0);

        $this->channel->sendReaction(
            $msg["message"]["id"],
            [
                "type" => "love",
                "count" => 42
            ],
            $this->user1["id"]
        );

        $this->channel->sendReaction(
            $msg["message"]["id"],
            [
                "type" => "clap",
            ],
            $this->user1["id"]
        );

        $response = $this->channel->getReactions($msg["message"]["id"]);
        $this->assertSame(count($response["reactions"]), 2);

        $response = $this->channel->getReactions(
            $msg["message"]["id"],
            [
                "offset" => 1
            ]
        );
        $this->assertSame(count($response["reactions"]), 1);
        $this->assertSame($response["reactions"][0]["count"], 42);
    }

    public function testSearch()
    {
        $query = "supercalifragilisticexpialidocious";
        $this->channel->sendMessage(["text" => "How many syllables are there in " . $query . "?"], $this->user1["id"]);
        $this->channel->sendMessage(["text" => "Does 'cious' count as one or two?"], $this->user1["id"]);
        $response = $this->client->search(
            //["type" => "messaging"],
            ["cid" => $this->channel->getCID()],
            $query,
            ["limit" => 2, "offset" => 0]
        );
        // searches all channels so make sure at least one is found
        $this->assertTrue(count($response['results']) >= 1);
        $this->assertTrue(strpos($response['results'][0]['message']['text'], $query) !== false);
        $response = $this->client->search(
            //["type" => "messaging"],
            ["cid" => $this->channel->getCID()],
            "cious",
            ["limit" => 12, "offset" => 0]
        );
        foreach ($response['results'] as $message) {
            $this->assertFalse(strpos($message['message']['text'], $query));
        }

        $response = $this->client->search(
            ["cid" => $this->channel->getCID()],
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
            ["sort" => [["created_at" => -1]], "offset" => 1]
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
        $query = "supercalifragilisticexpialidocious";
        $this->channel->sendMessage(["text" => "How many syllables are there in " . $query . "?"], $this->user1["id"]);
        $this->channel->sendMessage(["text" => "Does " . $query . " count as one or two?"], $this->user1["id"]);
        $response = $this->client->search(
            ["type" => "messaging"],
            $query,
            ["sort" => [["created_at" => -1]], "limit" => 1]
        );
        // searches all channels so make sure at least one is found
        $this->assertTrue(count($response['results']) >= 1);
        $this->assertTrue(strpos($response['results'][0]['message']['text'], $query) !== false);
        $response = $this->client->search(
            ["type" => "messaging"],
            $query,
            ["limit" => 1, "next" => $response['next']]
        );
        $this->assertTrue(count($response['results']) >= 1);
        $this->assertTrue(strpos($response['results'][0]['message']['text'], $query) !== false);
    }


    public function testGetMessage()
    {
        $org = $this->channel->sendMessage(["text" => "hi"], $this->user1["id"])['message'];
        $msg = $this->client->getMessage($org["id"])['message'];
        $this->assertSame($msg['id'], $org['id']);
        $this->assertSame($msg['text'], $org['text']);
        $this->assertSame($msg['user']['id'], $org['user']['id']);
    }

    public function testChannelSendAndDeleteFile()
    {
        $url = "https://stream-blog-v2.imgix.net/blog/wp-content/uploads/1f4a0a19b7533494c5341170abbf655e/stream_logo.svg";
        $resp = $this->channel->sendFile($url, "logo.svg", $this->user1);
        $this->assertTrue(strpos($resp['file'], "logo.svg") !== false);
        $resp = $this->channel->deleteFile($resp['file']);
    }

    public function testChannelSendAndDeleteImage()
    {
        $url = "https://getstream.io/images/icons/favicon-32x32.png";
        $resp = $this->channel->sendImage($url, "logo.png", $this->user1);
        $this->assertTrue(strpos($resp['file'], "logo.png") !== false);
        // $resp = $this->channel->deleteImage($resp['file']);
    }

    public function testChannelHideShow()
    {
        // setup
        $this->channel->addMembers([$this->user1['id'], $this->user2['id']]);
        // verify
        $response = $this->client->queryChannels(["id" => $this->channel->id]);
        $this->assertSame(count($response["channels"]), 1);
        $response = $this->client->queryChannels(["id" => $this->channel->id], null, ['user_id' => $this->user1["id"]]);
        $this->assertSame(count($response["channels"]), 1);
        // hide
        $response = $this->channel->hide($this->user1['id']);
        $response = $this->client->queryChannels(["id" => $this->channel->id], null, ['user_id' => $this->user1["id"]]);
        $this->assertSame(count($response["channels"]), 0);
        // search hidden channels
        $response = $this->client->queryChannels(["id" => $this->channel->id, "hidden" => true], null, ['user_id' => $this->user1["id"]]);
        $this->assertSame(count($response["channels"]), 1);
        // unhide
        $response = $this->channel->show($this->user1['id']);
        $response = $this->client->queryChannels(["id" => $this->channel->id], null, ['user_id' => $this->user1["id"]]);
        $this->assertSame(count($response["channels"]), 1);
        // hide again
        $response = $this->channel->hide($this->user1['id']);
        $response = $this->client->queryChannels(["id" => $this->channel->id], null, ['user_id' => $this->user1["id"]]);
        $this->assertSame(count($response["channels"]), 0);
        // send message
        $msgId = $this->generateGuid();
        $msg = ["id" => $msgId, "text" => "hello world"];
        $response = $this->channel->sendMessage($msg, $this->user2["id"]);
        // channel should be 'visible'
        $response = $this->client->queryChannels(["id" => $this->channel->id], null, ['user_id' => $this->user1["id"]]);
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

        $this->client->deleteUser($carmen["id"], ["user" => "hard", "messages" => "hard"]);
        $this->client->deleteUser($wally["id"], ["user" => "hard", "messages" => "hard"]);
    }

    public function testChannelMuteUnmute()
    {
        // setup
        $this->channel->addMembers([$this->user1['id']]);
        // verify
        $response = $this->client->queryChannels(["id" => $this->channel->id]);
        $this->assertSame(count($response["channels"]), 1);
        $response = $this->client->queryChannels(["id" => $this->channel->id], null, ['user_id' => $this->user1["id"]]);
        $this->assertSame(count($response["channels"]), 1);
        // mute
        $this->channel->mute($this->user1['id']);
        $response = $this->client->queryChannels(["muted" => true, "cid" => $this->channel->getCID()], null, ["user_id" => $this->user1["id"]]);
        $this->assertSame(count($response["channels"]), 1);
        // unmute
        $this->channel->unmute($this->user1['id']);
        $response = $this->client->queryChannels(["muted" => true, "cid" => $this->channel->getCID()], null, ["user_id" => $this->user1["id"]]);
        $this->assertSame(count($response["channels"]), 0);
        // mute with expiration
        $this->channel->mute($this->user1['id'], 10000);
        $response = $this->client->queryChannels(["muted" => true, "cid" => $this->channel->getCID()], null, ["user_id" => $this->user1["id"]]);
        $this->assertSame(count($response["channels"]), 1);
        sleep(10);
        $response = $this->client->queryChannels(["muted" => true, "cid" => $this->channel->getCID()], null, ["user_id" => $this->user1["id"]]);
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

    public function testImportEnd2End()
    {
        $urlResp = $this->client->createImportUrl("streamchatphp.json");
        $this->assertNotEmpty($urlResp['upload_url']);
        $this->assertNotEmpty($urlResp['path']);

        $guzzleClient = new GuzzleClient();
        $resp = $guzzleClient->put($urlResp['upload_url'], [
            'body' => "{}",
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
        $this->assertEquals(200, $resp->getStatusCode());

        $createResp = $this->client->createImport($urlResp['path'], "upsert");
        $this->assertNotEmpty($createResp['import_task']['id']);

        $getResp = $this->client->getImport($createResp['import_task']['id']);
        $this->assertEquals($createResp['import_task']['id'], $getResp['import_task']['id']);

        $listResp = $this->client->listImports(['limit' => 1]);
        $this->assertNotEmpty($listResp['import_tasks']);
    }

    public function testUnreadCounts()
    {
        $this->channel->addMembers([$this->user1["id"]]);
        $msgResp= $this->channel->sendMessage(["text" => "hi"], "random_user_4321");

        $resp = $this->client->unreadCounts($this->user1["id"]);
        $this->assertNotEmpty($resp["total_unread_count"]);
        $this->assertEquals(1, $resp["total_unread_count"]);
        $this->assertNotEmpty($resp["channels"]);
        $this->assertEquals(1, count($resp["channels"]));
        $this->assertEquals($this->channel->getCID(), $resp["channels"][0]["channel_id"]);

        // test unread thread counts
        $this->channel->sendMessage(["parent_id" => $msgResp["message"]["id"], "text" => "hi"], $this->user1["id"]);
        $this->channel->sendMessage(["parent_id" => $msgResp["message"]["id"], "text" => "hi"], "random_user_4321");
        $resp = $this->client->unreadCounts($this->user1["id"]);
        $this->assertNotEmpty($resp["total_unread_threads_count"]);
        $this->assertEquals(1, $resp["total_unread_threads_count"]);
    }

    public function testUnreadCountsBatch()
    {
        $this->channel->addMembers([$this->user1["id"]]);
        $this->channel->addMembers([$this->user2["id"]]);
        $msgResp = $this->channel->sendMessage(["text" => "hi"], "random_user_4321");

        $resp = $this->client->unreadCountsBatch([$this->user1["id"], $this->user2["id"]]);
        $this->assertNotEmpty($resp["counts_by_user"]);
        $this->assertEquals(2, count($resp["counts_by_user"]));
        $this->assertEquals(1, $resp["counts_by_user"][$this->user1["id"]]["total_unread_count"]);
        $this->assertEquals(1, $resp["counts_by_user"][$this->user2["id"]]["total_unread_count"]);

        // test unread thread counts
        $this->channel->sendMessage(["parent_id" => $msgResp["message"]["id"], "text" => "hi"], $this->user1["id"]);
        $this->channel->sendMessage(["parent_id" => $msgResp["message"]["id"], "text" => "hi"], $this->user2["id"]);
        $this->channel->sendMessage(["parent_id" => $msgResp["message"]["id"], "text" => "hi"], "random_user_4321");
        $resp = $this->client->unreadCountsBatch([$this->user1["id"], $this->user2["id"]]);
        $this->assertNotEmpty($resp["counts_by_user"][$this->user1["id"]]["total_unread_threads_count"]);
        $this->assertEquals(1, $resp["counts_by_user"][$this->user1["id"]]["total_unread_threads_count"]);
    }
}
