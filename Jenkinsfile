pipeline {
    agent any

    options {
        ansiColor('xterm')
    }

    environment {
        DEPLOYMENT_NAME = ""
        REPOSITORY_NAME = ""
        ECR_REGISTRY = "722465641242.dkr.ecr.eu-central-1.amazonaws.com"
        IMAGE_NAME = "${ECR_REGISTRY}/${REPOSITORY_NAME}"
        GIT_SHORT = sh (script: '''git log -1 --pretty=format:%h''', returnStdout:true)
        COMMIT_MESSAGE = sh (script: '''git log -1 --pretty=format:%s''', returnStdout:true)
        GITHUB_OAUTH = credentials('GITHUB_OAUTH')
    }

    stages {
        stage("Build Test Image") {
            when {
                expression { env.BRANCH_NAME.startsWith('PR-') }
            }

            steps{
                withCredentials([[
                    $class: "AmazonWebServicesCredentialsBinding",
                    credentialsId: "jenkins-ecr-user",
                    accessKeyVariable: "AWS_ACCESS_KEY_ID",
                    secretKeyVariable: "AWS_SECRET_ACCESS_KEY"
                ]]) {
                    script {
                        sh '''
                            aws ecr get-login-password --region eu-central-1 | docker login --username AWS --password-stdin ${ECR_REGISTRY}

                            # Check if the buildx driver exists if not create it.
                            if docker buildx ls | grep -q 'buildx-container'; then
                                echo "Docker Buildx driver 'buildx-container' already exists."
                            else
                                echo "Docker Buildx driver 'buildx-container' does not exist. Creating..."
                                docker buildx create --name buildx-container --driver docker-container
                            fi

                            docker buildx build --load -t ${IMAGE_NAME}:test \
                            --builder=buildx-container \
                            --provenance=false \
                            --cache-from type=registry,ref=${IMAGE_NAME}:cache \
                            --build-arg APP_ENV=test \
                            -f docker/php/Dockerfile .
                        '''
                    }
                }
            }
        }

        stage('Install dev dependencies') {
            when {
                expression { env.BRANCH_NAME.startsWith('PR-') }
            }

            steps {
                script {
                    docker.image("$IMAGE_NAME:test").inside() {
                        sh '''
                            composer install
                        '''
                    }
                }
            }
        }

        stage('Test') {
            when {
                expression { env.BRANCH_NAME.startsWith('PR-') }
            }

            steps {
                script {
                    docker.image('mysql:8.0').withRun('-e "MYSQL_ALLOW_EMPTY_PASSWORD=yes"', '--sql-mode=STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION') { wd ->
                       docker.image('mysql:8.0').inside("--link ${wd.id}:database") {
                           /* Wait until mysql service is up */
                           sh 'while ! mysqladmin ping -hdatabase --silent; do sleep 1; done'
                       }
                       docker.image("$IMAGE_NAME:test").inside("--link ${wd.id}:database -eAPP_ENV=test") {
                           sh '''
                               php ./bin/phpunit
                           '''
                       }
                   }
                }
            }

            post {
                always {
                    jiraSendBuildInfo site: 'roadsurfer.atlassian.net'
                }
            }
        }

        stage("Build And Publish") {
            when {
                branch "master"
            }

            steps {
                withCredentials([[
                    $class: "AmazonWebServicesCredentialsBinding",
                    credentialsId: "jenkins-ecr-user",
                    accessKeyVariable: "AWS_ACCESS_KEY_ID",
                    secretKeyVariable: "AWS_SECRET_ACCESS_KEY"
                ]]) {
                    script {
                        sh '''
                            aws ecr get-login-password --region eu-central-1 | docker login --username AWS --password-stdin ${ECR_REGISTRY}

                            # Check if the buildx driver exists if not create it.
                            if docker buildx ls | grep -q 'buildx-container'; then
                                echo "Docker Buildx driver 'buildx-container' already exists."
                            else
                                echo "Docker Buildx driver 'buildx-container' does not exist. Creating..."
                                docker buildx create --name buildx-container --driver docker-container
                            fi

                            docker buildx build --push -t ${IMAGE_NAME}:latest -t ${IMAGE_NAME}:${GIT_SHORT} \
                            --builder=buildx-container \
                            --provenance=false \
                            --cache-to mode=max,image-manifest=true,oci-mediatypes=true,type=registry,ref=${IMAGE_NAME}:cache \
                            --cache-from type=registry,ref=${IMAGE_NAME}:cache \
                            --build-arg APP_ENV=prod \
                            -f docker/php/Dockerfile .
                        '''
                    }
                }
            }
        }

        stage("Staging Deploy") {
            when {
                branch "master"
            }

            steps {
                withAWS(role:'eks-admin', roleAccount:'589767178493', credentials:'jenkins-ec2-user') {
                    script {
                        sh '''
                            aws eks --region eu-central-1 update-kubeconfig --name cluster-staging
                            helm upgrade --install --namespace staging ${DEPLOYMENT_NAME}  ./deployment \
                                --set env.app_env=${STAGING_APP_ENV}
                        '''
                    }
                }

                office365ConnectorSend (
                    color: '#00FF00',
                    message: "${DEPLOYMENT_NAME} ${GIT_SHORT} has been successfully deployed on staging",
                    webhookUrl: 'https://roadsurfer.webhook.office.com/webhookb2/8d75c9f5-c5d0-49dc-8d22-5a32f840a8c7@573ae0ae-0a5d-4098-8813-0f140a1c85da/JenkinsCI/bc838cfcc93b4690a716e3fb636f5fc5/686da0f9-7f13-4d6b-86e0-afa1977e8c24',
                    factDefinitions:[
                        [ name: "Commit Message", template: "${COMMIT_MESSAGE}"],
                    ]
                )
            }

            post {
                always {
                    jiraSendDeploymentInfo site: 'roadsurfer.atlassian.net', environmentId: 'staging-eu', environmentName: 'Staging EU', environmentType: 'staging'
                }

                success {
                    script {
                        build wait: false,
                        propagate: false,
                        job: 'roadsurfer-com/rsf-automation/master',
                        parameters: [string(name: 'repoName', value: "${DEPLOYMENT_NAME}")]
                    }
                }
            }
        }

        stage("Verify Prod Deploy") {
            when {
                branch "master"
            }

            steps {
                timeout(time: 1, unit: 'HOURS') {
                    input message: 'Deploy to Production?', ok: 'Deploy'
                }
            }
        }

        stage("Production Deploy") {
            when {
                branch "master"
            }

            steps {
                withAWS(role:'eks-admin-full-prod', roleAccount:'589767178493', credentials:'jenkins-ec2-user') {
                    script {
                        sh '''
                            aws eks --region eu-central-1 update-kubeconfig --name cluster-prod
                            helm upgrade --namespace production -f ./deployment/values-prod.yaml ${DEPLOYMENT_NAME} ./deployment \
                                --set env.app_env=${PROD_APP_ENV}
                        '''
                    }
                }

                office365ConnectorSend (
                    color: '#00FF00',
                    message: "${DEPLOYMENT_NAME} ${GIT_SHORT} has been successfully deployed on production",
                    webhookUrl: 'https://roadsurfer.webhook.office.com/webhookb2/8d75c9f5-c5d0-49dc-8d22-5a32f840a8c7@573ae0ae-0a5d-4098-8813-0f140a1c85da/JenkinsCI/2a9c2f9977374998a46eb384c094ca62/686da0f9-7f13-4d6b-86e0-afa1977e8c24',
                    factDefinitions:[
                        [ name: "Commit Message", template: "${COMMIT_MESSAGE}"],
                    ]
                )
            }

            post {
                always {
                    jiraSendDeploymentInfo site: 'roadsurfer.atlassian.net', environmentId: 'prod-eu', environmentName: 'Production EU', environmentType: 'production'
                }
            }
        }
    }

    post {
        always {
            sh 'docker rmi -f ${IMAGE_NAME}:test'
            cleanWs()
        }
    }
}
